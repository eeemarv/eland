<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersPasswordEditCommand;
use App\Form\Type\Users\UsersPasswordEditType;
use App\Queue\MailQueue;
use App\Repository\UserRepository;
use App\Security\User;
use App\Service\AlertService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class UsersPasswordEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/password-edit',
        name: 'users_password_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'is_self'       => false,
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/{id}/password-edit-self',
        name: 'users_password_edit_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'id'            => 0,
            'is_self'       => true,
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        EncoderFactoryInterface $encoder_factory,
        int $id,
        bool $is_self,
        UserRepository $user_repository,
        AlertService $alert_service,
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if ($is_self)
        {
            $id = $su->id();
        }

        $user = $user_cache_service->get($id, $pp->schema());
        $is_active = $user['status'] === 1 || $user['status'] === 2;

        $to_mail_addr = $mail_addr_user_service->get_active($id, $pp->schema());
        $has_email = count($to_mail_addr) > 0;

        $form_options = [
            'validation_groups' => [$pp->role()],
        ];

        $command = new UsersPasswordEditCommand();
        $command->notify = true;
        $form = $this->createForm(UsersPasswordEditType::class,
            $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $encoder = $encoder_factory->getEncoder(new User());
            $hashed_password = $encoder->encodePassword($command->password, null);
            $user_repository->set_password($id, $hashed_password, $pp->schema());

            $alert_service->success('Paswoord opgeslagen.');

            if ($command->notify)
            {
                if ($is_active && $has_email)
                {
                    $vars = [
                        'user_id'		=> $id,
                        'password'		=> $command->password,
                    ];

                    $mail_queue->queue([
                        'schema'	=> $pp->schema(),
                        'to' 		=> $to_mail_addr,
                        'reply_to'	=> $mail_addr_system_service->get_support($pp->schema()),
                        'template'	=> 'password_reset/user',
                        'vars'		=> $vars,
                    ], 8000);

                    $alert_service->success('Notificatie mail verzonden');
                }
                else if (!$has_email)
                {
                    $alert_service->warning('Geen E-mail adres bekend voor deze gebruiker, stuur het paswoord op een andere manier door!');
                }
                else
                {
                    $alert_service->warning('Er werd geen notificatie email verstuurd want het account is niet actief.');
                }
            }

            if ($is_self)
            {
                return $this->redirectToRoute('users_show_self', $pp->ary());
            }

            return $this->redirectToRoute('users_show', [
                ...$pp->ary(),
                'id' => $id,
            ]);
        }

        return $this->render('users/users_password_edit.html.twig', [
            'form'              => $form->createView(),
            'is_self'           => $is_self,
            'id'                => $id,
            'is_active'         => $is_active,
            'has_email'         => $has_email,
            'notify_enabled'    => $is_active && $has_email,
        ]);
    }
}
