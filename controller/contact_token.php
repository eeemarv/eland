<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class contact_token
{
    public function contact_token(app $app, string $token):Response
    {
        if (!$app['config']->get('contact_form_en', $app['pp_schema']))
        {
            $app['alert']->warning('De contactpagina is niet ingeschakeld.');
            $app['link']->redirect('login', $app['pp_ary'], []);
        }

        $data = $app['data_token']->retrieve($token, 'contact', $app['pp_schema']);

        if (!$data)
        {
            $app['alert']->error('Ongeldig of verlopen token.');
            $app['link']->redirect('contact', $app['pp_ary'], []);
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

        $app['alert']->success('Je bericht werd succesvol verzonden.');
        $app['link']->redirect('contact', $app['pp_ary'], []);

        return new Response();
    }
}
