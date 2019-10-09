<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;

class ContactTokenController extends AbstractController
{
    public function contact_token(app $app, string $token):Response
    {
        if (!$config_service->get('contact_form_en', $app['pp_schema']))
        {
            $alert_service->warning('De contactpagina is niet ingeschakeld.');
            $link_render->redirect('login', $app['pp_ary'], []);
        }

        $data = $app['data_token']->retrieve($token, 'contact', $app['pp_schema']);

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

        $app['queue.mail']->queue([
            'schema'	=> $app['pp_schema'],
            'template'	=> 'contact/copy',
            'vars'		=> $vars,
            'to'		=> [$data['email'] => $data['email']],
        ], 9000);

        $app['queue.mail']->queue([
            'schema'	=> $app['pp_schema'],
            'template'	=> 'contact/support',
            'vars'		=> $vars,
            'to'		=> $app['mail_addr_system']->get_support($app['pp_schema']),
            'reply_to'	=> [$data['email']],
        ], 8000);

        $app['data_token']->del($token, 'contact', $app['pp_schema']);

        $alert_service->success('Je bericht werd succesvol verzonden.');
        $link_render->redirect('contact', $app['pp_ary'], []);

        return new Response();
    }
}
