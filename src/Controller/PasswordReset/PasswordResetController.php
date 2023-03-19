<?php declare(strict_types=1);

namespace App\Controller\PasswordReset;

use App\Command\PasswordReset\PasswordResetCommand;
use App\Form\Type\PasswordReset\PasswordResetType;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Repository\UserRepository;
use App\Service\AlertService;
use App\Service\DataTokenService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class PasswordResetController extends AbstractController
{
    #[Route(
        '/{system}/password-reset',
        name: 'password_reset',
        methods: ['GET', 'POST'],
        priority: 30,
        requirements: [
            'system'        => '%assert.system%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'password_reset',
        ],
    )]

    public function __invoke(
        Request $request,
        UserRepository $user_repository,
        AccountRender $account_render,
        AlertService $alert_service,
        DataTokenService $data_token_service,
        MailQueue $mail_queue,
        PageParamsService $pp
    ):Response
    {
        $command = new PasswordResetCommand();

        $form_options = [
            'validation_groups' => ['send'],
        ];

        $form = $this->createForm(PasswordResetType::class, $command, $form_options);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $email = strtolower($command->email);

            $user_id = $user_repository->get_active_id_by_email($email, $pp->schema());

            $token = $data_token_service->store([
                'user_id'	=> $user_id,
                'email'		=> $email,
            ], 'password_reset', $pp->schema(), 86400);

            $account_str = $account_render->get_str($user_id, $pp->schema());

            $mail_queue->queue([
                'schema'	=> $pp->schema(),
                'to' 		=> [new Address($email, $account_str)],
                'template'	=> 'password_reset/confirm',
                'vars'		=> [
                    'token'			=> $token,
                    'user_id'		=> $user_id,
                ],
            ], 10000);

            $alert_service->success('Een link om je paswoord te resetten werd
                naar je E-mailbox verzonden. Deze link blijft 24 uur geldig.');

            return $this->redirectToRoute('login', $pp->ary());
        }

        return $this->render('password_reset/password_reset.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
