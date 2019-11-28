<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\MailQueue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\MailAddrSystemService;
use App\Service\PageParamsService;

class ContactFormTokenController extends AbstractController
{
    public function __invoke(
        string $token,
        ConfigService $config_service,
        LinkRender $link_render,
        AlertService $alert_service,
        DataTokenService $data_token_service,
        MailAddrSystemService $mail_addr_system_service,
        PageParamsService $pp,
        MailQueue $mail_queue
    ):Response
    {
        if (!$config_service->get('contact_form_en', $pp->schema()))
        {
            $alert_service->warning('De contactpagina is niet ingeschakeld.');
            $link_render->redirect('login', $pp->ary(), []);
        }

        $data = $data_token_service->retrieve($token, 'contact', $pp->schema());

        if (!$data)
        {
            $alert_service->error('Ongeldig of verlopen token.');
            $link_render->redirect('contact', $pp->ary(), []);
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
            'to'		=> [$data['email'] => $data['email']],
        ], 9000);

        $mail_queue->queue([
            'schema'	=> $pp->schema(),
            'template'	=> 'contact/support',
            'vars'		=> $vars,
            'to'		=> $mail_addr_system_service->get_support($pp->schema()),
            'reply_to'	=> [$data['email']],
        ], 8000);

        $data_token_service->del($token, 'contact', $pp->schema());

        $alert_service->success('Je bericht werd succesvol verzonden.');
        $link_render->redirect('contact', $pp->ary(), []);

        return new Response();
    }
}
