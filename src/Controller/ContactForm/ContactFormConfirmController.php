<?php declare(strict_types=1);

namespace App\Controller\ContactForm;

use App\Queue\MailQueue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\MailAddrSystemService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ContactFormConfirmController extends AbstractController
{
    #[Route(
        '/{system}/contact/{token}',
        name: 'contact_form_confirm',
        methods: ['GET'],
        priority: 10,
        requirements: [
            'token'         => '%assert.token%',
            'system'        => '%assert.system%',
        ],
        defaults: [
            'module'        => 'contact_form',
        ],
    )]

    public function __invoke(
        string $token,
        ConfigService $config_service,
        AlertService $alert_service,
        DataTokenService $data_token_service,
        MailAddrSystemService $mail_addr_system_service,
        PageParamsService $pp,
        MailQueue $mail_queue
    ):Response
    {
        if (!$config_service->get_bool('contact_form.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Contact form module not enabled.');
        }

        $data = $data_token_service->retrieve($token, 'contact_form', $pp->schema());

        if (!$data)
        {
            $alert_service->error('Ongeldig of verlopen token.');
            return $this->redirectToRoute('contact_form', $pp->ary());
        }

        $vars = [
            'message'		=> $data['message'],
            'ip'			=> $data['ip'],
            'agent'			=> $data['agent'],
            'email'			=> $data['email'],
        ];

        $mail_queue->queue([
            'schema'	=> $pp->schema(),
            'template'	=> 'contact/copy',
            'vars'		=> $vars,
            'to'		=> [new Address($data['email'])],
        ], 9000);

        $mail_queue->queue([
            'schema'	=> $pp->schema(),
            'template'	=> 'contact/support',
            'vars'		=> $vars,
            'to'		=> $mail_addr_system_service->get_support($pp->schema()),
            'reply_to'	=> [new Address($data['email'])],
        ], 8000);

        $data_token_service->del($token, 'contact_form', $pp->schema());

        $alert_service->success('Je bericht werd succesvol verzonden.');
        return $this->redirectToRoute('contact_form', $pp->ary());
    }
}
