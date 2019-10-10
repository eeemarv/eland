<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\MailQueue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\DataTokenService;

class ContactTokenController extends AbstractController
{
    public function contact_token(
        string $token,
        ConfigService $config_service,
        LinkRender $link_render,
        AlertService $alert_service,
        DataTokenService $data_token_service,
        MailQueue $mail_queue
    ):Response
    {
        if (!$config_service->get('contact_form_en', $app['pp_schema']))
        {
            $alert_service->warning('De contactpagina is niet ingeschakeld.');
            $link_render->redirect('login', $app['pp_ary'], []);
        }

        $data = $data_token_service->retrieve($token, 'contact', $app['pp_schema']);

        if (!$data)
        {
            $alert_service->error('Ongeldig of verlopen token.');
            $link_render->redirect('contact', $app['pp_ary'], []);
        }

        $vars = [
            'message'		=> $data['message'],
            'ip'			=> $data['ip'],
            'agent'			=> $data['agent'],
            'email'			=> $data['email'],
        ];

        $mail_queue->queue([
            'schema'	=> $app['pp_schema'],
            'template'	=> 'contact/copy',
            'vars'		=> $vars,
            'to'		=> [$data['email'] => $data['email']],
        ], 9000);

        $mail_queue->queue([
            'schema'	=> $app['pp_schema'],
            'template'	=> 'contact/support',
            'vars'		=> $vars,
            'to'		=> $mail_addr_system_service->get_support($app['pp_schema']),
            'reply_to'	=> [$data['email']],
        ], 8000);

        $data_token_service->del($token, 'contact', $app['pp_schema']);

        $alert_service->success('Je bericht werd succesvol verzonden.');
        $link_render->redirect('contact', $app['pp_ary'], []);

        return new Response();
    }
}
