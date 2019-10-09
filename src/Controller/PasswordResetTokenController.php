<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class PasswordResetTokenController extends AbstractController
{
    public function password_reset_token(
        Request $request,
        app $app,
        string $token,
        Db $db
    ):Response
    {
        $data = $data_token_service->retrieve($token, 'password_reset', $app['pp_schema']);
        $password = $request->request->get('password', '');

        if (!$data)
        {
            $alert_service->error('Het reset-token is niet meer geldig.');
            $link_render->redirect('password_reset', $app['pp_ary'], []);
        }

        $user_id = $data['user_id'];

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
            }
            else if (!($app['password_strength']->get($password) < 50))
            {
                $db->update($app['pp_schema'] . '.users',
                    ['password' => hash('sha512', $password)],
                    ['id' => $user_id]);

                $user_cache_service->clear($user_id, $app['pp_schema']);
                $alert_service->success('Paswoord opgeslagen.');

                $mail_queue->queue([
                    'schema'	=> $app['pp_schema'],
                    'to' 		=> $app['mail_addr_user']->get_active($user_id, $app['pp_schema']),
                    'template'	=> 'password_reset/user',
                    'vars'		=> [
                        'password'		=> $password,
                        'user_id'		=> $user_id,
                    ],
                ], 10000);

                $data = $data_token_service->del($token, 'password_reset', $app['pp_schema']);
                $link_render->redirect('login', $app['pp_ary'], []);
            }
            else
            {
                $alert_service->error('Het paswoord is te zwak.');
            }
        }

        $heading_render->add('Nieuw paswoord ingeven.');
        $heading_render->fa('key');

        $assets_service->add([
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
        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('login');

        return $this->render('base/sidebar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
