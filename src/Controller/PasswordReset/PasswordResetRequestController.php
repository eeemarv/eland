<?php declare(strict_types=1);

namespace App\Controller\PasswordReset;

use App\Command\PasswordReset\PasswordResetRequestCommand;
use App\Form\Post\PasswordReset\PasswordResetRequestType;
use App\Queue\MailQueue;
use App\Render\LinkRender;
use App\Repository\UserRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PasswordResetRequestController extends AbstractController
{
    public function __invoke(
        Request $request,
        UserRepository $user_repository,
        UserCacheService $user_cache_service,
        AlertService $alert_service,
        ConfigService $config_service,
        DataTokenService $data_token_service,
        MailQueue $mail_queue,
        LinkRender $link_render,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $form_disabled = false;

        if (!$config_service->get('mailenabled', $pp->schema()))
        {
            $alert_service->warning('email.warning.disabled');
            $form_disabled = true;
        }

        $password_reset_request_command = new PasswordResetRequestCommand();

        $form_options = $form_disabled ? ['disabled' => true] : [];

        $form = $this->createForm(PasswordResetRequestType::class,
                $password_reset_request_command, $form_options)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid()
            && !$form_disabled)
        {
            $password_reset_request_command = $form->getData();
            $email = $password_reset_request_command->email;
            $email_lowercase = strtolower($email);

            $user_id = $user_repository->get_active_id_by_eamil($email_lowercase, $pp->schema());
            $user = $user_cache_service->get($user_id, $pp->schema());

            $token = $data_token_service->store([
                'user_id'	=> $user_id,
            ], 'password_reset', $pp->schema(), 86400);

            $mail_queue->queue([
                'schema'	=> $pp->schema(),
                'to' 		=> [$email => $user['code'] . ' ' . $user['name']],
                'template'	=> 'password_reset/confirm',
                'vars'		=> [
                    'token'			=> $token,
                    'user_id'		=> $user_id,
                ],
            ], 10000);

            $alert_service->success('password_reset_request.success.email_sent');
            $link_render->redirect('login', $pp->ary(), []);
        }

        $menu_service->set('login');

        return $this->render('password_reset/password_reset_request.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}