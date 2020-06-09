<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersPasswordSetAdminCommand;
use App\Form\Post\Users\UsersPasswordSetType;
use App\Queue\MailQueue;
use App\Render\AccountRender;
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

class UsersPasswordSetAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        EncoderFactoryInterface $encoder_factory,
        UserRepository $user_repository,
        int $id,
        AlertService $alert_service,
        LinkRender $link_render,
        AccountRender $account_render,
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        $mail_addr = $mail_addr_user_service->get_active($id, $pp->schema());

        $users_password_set_admin_command = new UsersPasswordSetAdminCommand();

        $notify_enabled = count($mail_addr) > 0;

        if ($notify_enabled)
        {
            $users_password_set_admin_command->notify = true;
        }

        $form = $this->createForm(UsersPasswordSetType::class, $users_password_set_admin_command)
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $users_password_set_admin_command = $form->getData();
            $password = $users_password_set_admin_command->password;
            $notify = $users_password_set_admin_command->notify;
            $notify = isset($notify) && $notify;

            $encoder = $encoder_factory->getEncoder(new User());
            $hashed_password = $encoder->encodePassword($password, null);

            $user_repository->set_password($id, $hashed_password, $pp->schema());

            $success_trans_key = 'users_password_set.success.';

            if ($notify && count($mail_addr))
            {
                $vars = [
                    'user_id'		=> $id,
                    'password'		=> $password,
                ];

                $mail_queue->queue([
                    'schema'	=> $pp->schema(),
                    'to' 		=> $mail_addr,
                    'reply_to'	=> $mail_addr_system_service->get_support($pp->schema()),
                    'template'	=> 'password_reset/user',
                    'vars'		=> $vars,
                ], 8000);

                $success_trans_key .= 'with_notify.';
            }
            else
            {
                $success_trans_key .= 'without_notify.';
            }

            $success_trans_key .= $su->is_owner($id) ? 'personal' : 'admin';

            $alert_service->success($success_trans_key, [
                '%user%'    => $account_render->get_str($id, $pp->schema()),
            ]);

            $link_render->redirect('users_show', $pp->ary(), ['id' => $id]);
        }

        $menu_service->set('users');

        return $this->render('users/users_password_set.html.twig', [
            'form'              => $form->createView(),
            'user_id'           => $id,
            'notify_enabled'    => $notify_enabled,
            'schema'            => $pp->schema(),
        ]);
    }
}
