<?php declare(strict_types=1);

namespace App\Controller\PasswordReset;

use App\Cnst\PagesCnst;
use App\Command\PasswordReset\PasswordResetConfirmCommand;
use App\Form\Type\PasswordReset\PasswordResetConfirmType;
use App\Queue\MailQueue;
use App\Repository\UserRepository;
use App\Security\User;
use App\Service\AlertService;
use App\Service\DataTokenService;
use App\Service\MailAddrUserService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class PasswordResetConfirmController extends AbstractController
{
    #[Route(
        '/{system}/password-reset/{token}',
        name: 'password_reset_confirm',
        methods: ['GET', 'POST'],
        priority: 30,
        requirements: [
            'token'         => '%assert.token%',
            'system'        => '%assert.system%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'password_reset',
        ],
    )]

    public function __invoke(
        Request $request,
        PasswordHasherFactoryInterface $password_hasher_factory,
        string $token,
        UserRepository $user_repository,
        DataTokenService $data_token_service,
        AlertService $alert_service,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        $form_disabled = false;

        if ($pp->edit_en()
            && $token === PagesCnst::CMS_TOKEN
            && $su->is_admin())
        {
            $user_id = $su->id();
            $form_disabled = true;
        }
        else
        {
            $data = $data_token_service->retrieve($token, 'password_reset', $pp->schema());

            if (!$data)
            {
                $alert_service->error('Het reset-token is niet meer geldig.');

                return $this->redirectToRoute('password_reset', $pp->ary());
            }

            $user_id = $data['user_id'];
        }

        $command = new PasswordResetConfirmCommand();

        $form_options = [
            'validation_groups' => ['edit'],
            'disabled' => $form_disabled,
        ];

        $form = $this->createForm(PasswordResetConfirmType::class,
            $command, $form_options);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $password_hasher = $password_hasher_factory->getPasswordHasher(new User());
            $hashed_password = $password_hasher->hash($command->password);

            $user_repository->set_password($user_id, $hashed_password, $pp->schema());

            $mail_queue->queue([
                'schema'	=> $pp->schema(),
                'to' 		=> $mail_addr_user_service->get_active($user_id, $pp->schema()),
                'template'	=> 'password_reset/user',
                'vars'		=> [
                    'password'		=> $command->password,
                    'user_id'		=> $user_id,
                ],
            ], 10000);

            $data = $data_token_service->del($token, 'password_reset', $pp->schema());

            $alert_service->success('Paswoord opgeslagen.');
            return $this->redirectToRoute('login', $pp->ary());
        }

        return $this->render('password_reset/password_reset_confirm.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
