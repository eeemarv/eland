<?php declare(strict_types=1);

namespace App\Controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class password_reset_token
{
    public function password_reset_token(Request $request, app $app, string $token):Response
    {
        $data = $app['data_token']->retrieve($token, 'password_reset', $app['pp_schema']);
        $password = $request->request->get('password', '');

        if (!$data)
        {
            $app['alert']->error('Het reset-token is niet meer geldig.');
            $app['link']->redirect('password_reset', $app['pp_ary'], []);
        }

        $user_id = $data['user_id'];

        if ($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
            }
            else if (!($app['password_strength']->get($password) < 50))
            {
                $app['db']->update($app['pp_schema'] . '.users',
                    ['password' => hash('sha512', $password)],
                    ['id' => $user_id]);

                $app['user_cache']->clear($user_id, $app['pp_schema']);
                $app['alert']->success('Paswoord opgeslagen.');

                $app['queue.mail']->queue([
                    'schema'	=> $app['pp_schema'],
                    'to' 		=> $app['mail_addr_user']->get_active($user_id, $app['pp_schema']),
                    'template'	=> 'password_reset/user',
                    'vars'		=> [
                        'password'		=> $password,
                        'user_id'		=> $user_id,
                    ],
                ], 10000);

                $data = $app['data_token']->del($token, 'password_reset', $app['pp_schema']);
                $app['link']->redirect('login', $app['pp_ary'], []);
            }
            else
            {
                $app['alert']->error('Het paswoord is te zwak.');
            }
        }

        $app['heading']->add('Nieuw paswoord ingeven.');
        $app['heading']->fa('key');

        $app['assets']->add([
            'generate_password.js',
        ]);

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post" role="form">';

        $out .= '<div class="form-group">';
        $out .= '<label for="password">Nieuw paswoord</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-key"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="password" name="password" ';
        $out .= 'value="';
        $out .= $password;
        $out .= '" required>';
        $out .= '<span class="input-group-btn">';
        $out .= '<button class="btn btn-default" type="button" ';
        $out .= 'data-generate-password>Genereer</button>';
        $out .= '</span>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<input type="submit" class="btn btn-primary btn-lg" value="Bewaar paswoord" name="zend">';
        $out .= $app['form_token']->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('login');

        return $app->render('base/sidebar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
