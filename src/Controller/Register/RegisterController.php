<?php declare(strict_types=1);

namespace App\Controller\Register;

use App\Command\Register\RegisterCommand;
use App\Form\Post\Register\RegisterType;
use App\Queue\MailQueue;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RegisterController extends AbstractController
{
    public function __invoke(
        Request $request,
        LoggerInterface $logger,
        MailQueue $mail_queue,
        MenuService $menu_service,
        DataTokenService $data_token_service,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp,
        LinkRender $link_render
    ):Response
    {
        $form_disabled = false;

        if (!$config_service->get('mailenabled', $pp->schema()))
        {
            $alert_service->warning('email.warning.disabled');
            $form_disabled = true;
        }

        $register_command = new RegisterCommand();

        $form_options = $form_disabled ? ['disabled' => true] : [];

        $form = $this->createForm(RegisterType::class,
                $register_command, $form_options)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid()
            && !$form_disabled)
        {
            $register_command = $form->getData();
            $email = $register_command->email;
            $first_name = $register_command->first_name;
            $last_name = $register_command->last_name;
            $postcode = $register_command->postcode;
            $mobile = $register_command->mobile;
            $phone = $register_command->phone;

            $register_data = [
                'email'			=> $email,
                'first_name'	=> $first_name,
                'last_name'		=> $last_name,
                'postcode'		=> $postcode,
                'phone'			=> $phone,
                'mobile'		=> $mobile,
            ];

            $logger->info('Registration request for ' .
                $register_data['email'], ['schema' => $pp->schema()]);

            $token = $data_token_service->store($register_data,
                'register', $pp->schema(), 604800); // 1 week

            $mail_queue->queue([
                'schema'	=> $pp->schema(),
                'to' 		=> [$email => $first_name . ' ' . $last_name],
                'vars'		=> ['token' => $token],
                'template'	=> 'register/register_confirm',
            ], 10000);

            $alert_service->success('register.success.link_sent');

            $link_render->redirect('login', $pp->ary(), []);
        }

        $menu_service->set('register');

        return $this->render('register/register.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
