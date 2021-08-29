<?php declare(strict_types=1);

namespace App\Controller\RegisterForm;

use App\Command\RegisterForm\RegisterFormCommand;
use App\Form\Type\RegisterForm\RegisterFormType;
use App\Queue\MailQueue;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class RegisterFormController extends AbstractController
{
    #[Route(
        '/{system}/register',
        name: 'register_form',
        methods: ['GET', 'POST'],
        priority: 30,
        requirements: [
            'system'        => '%assert.system%',
        ],
        defaults: [
            'module'        => 'register_form',
        ],
    )]

    public function __invoke(
        Request $request,
        LoggerInterface $logger,
        MailQueue $mail_queue,
        DataTokenService $data_token_service,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('register_form.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Register form not enabled.');
        }

        $postcode_enabled = $config_service->get_bool('users.fields.postcode.enabled', $pp->schema());

        $command = new RegisterFormCommand();
        $form = $this->createForm(RegisterFormType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $email = strtolower($command->email);
            $first_name = $command->first_name;
            $last_name = $command->last_name;
            $postcode = $command->postcode;
            $phone = $command->phone;
            $mobile = $command->mobile;

            $full_name = $first_name . ' ' . $last_name;

            $logger->info('Registration request for ' .
                $email, ['schema' => $pp->schema()]);

            $reg = [
                'email'         => $email,
                'first_name'    => $first_name,
                'last_name'     => $last_name,
                'full_name'     => $full_name,
                'tel'           => $phone,
                'gsm'           => $mobile,
            ];

            if ($postcode_enabled)
            {
                $reg['postcode'] = $postcode;
            }

            $token = $data_token_service->store($reg,
                'register_form', $pp->schema(), 604800); // 1 week

            $mail_queue->queue([
                'schema'	=> $pp->schema(),
                'to' 		=> [$email => $full_name],
                'vars'		=> ['token' => $token],
                'template'	=> 'register/confirm',
            ], 10000);

            $alert_service->success('Open je E-mailbox en klik op de
                bevestigingslink in de E-mail die we naar je gestuurd
                hebben om je inschrijving te voltooien.');

            return $this->redirectToRoute('login', $pp->ary());
        }

        return $this->render('register_form/register_form.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
