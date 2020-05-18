<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersPasswordSetCommand;
use App\Form\Post\Users\UsersPasswordSetType;
use App\Queue\MailQueue;
use App\Render\LinkRender;
use App\Repository\UserRepository;
use App\Security\User;
use App\Service\AlertService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UsersPasswordSetController extends AbstractController
{
    public function __invoke(
        Request $request,
        EncoderFactoryInterface $encoder_factory,
        UserRepository $user_repository,
        AlertService $alert_service,
        LinkRender $link_render,
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        $mail_addr = $mail_addr_user_service->get_active($su->id(), $pp->schema());

        $users_password_set_command = new UsersPasswordSetCommand();

        $notify_enabled = count($mail_addr) > 0;

        if ($notify_enabled)
        {
            $users_password_set_command->notify = true;
        }

        $form = $this->createForm(UsersPasswordSetType::class, $users_password_set_command)
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $users_password_set_command = $form->getData();
            $password = $users_password_set_command->password;
            $notify = $users_password_set_command->notify;
            $notify = isset($notify) && $notify;

            $encoder = $encoder_factory->getEncoder(new User());
            $hashed_password = $encoder->encodePassword($password, null);

            $user_repository->set_password($su->id(), $hashed_password, $pp->schema());

            if ($notify && count($mail_addr))
            {
                $vars = [
                    'user_id'		=> $su->id(),
                    'password'		=> $password,
                ];

                $mail_queue->queue([
                    'schema'	=> $pp->schema(),
                    'to' 		=> $mail_addr,
                    'reply_to'	=> $mail_addr_system_service->get_support($pp->schema()),
                    'template'	=> 'password_reset/user',
                    'vars'		=> $vars,
                ], 8000);

                $alert_service->success('users_password_set.success.password_set_with_notify');
            }
            else
            {
                $alert_service->success('users_password_set.success.password_set');
            }

            $link_render->redirect('users_show', $pp->ary(), ['id' => $su->id()]);
        }

        $menu_service->set('users');

        return $this->render('users/users_password_set.html.twig', [
            'form'              => $form->createView(),
            'notify_enabled'    => $notify_enabled,
            'schema'            => $pp->schema(),
        ]);
    }
}
