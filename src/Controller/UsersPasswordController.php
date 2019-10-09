<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class UsersPasswordController extends AbstractController
{
    public function users_password(
        Request $request,
        app $app,
        Db $db
    ):Response
    {
        return $this->users_password_admin($request, $app, $app['s_id'], $db);
    }

    public function users_password_admin(
        Request $request,
        app $app,
        int $id,
        Db $db
    ):Response
    {
        $password = trim($request->request->get('password', ''));
        $notify = $request->request->get('notify', '');

        if($request->isMethod('POST'))
        {
            if ($password === '')
            {
                $errors[] = 'Vul paswoord in!';
            }

            if (!$app['pp_admin']
                && $app['password_strength']->get($password) < 50)
            {
                $errors[] = 'Te zwak paswoord.';
            }

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $update = [
                    'password'	=> hash('sha512', $password),
                    'mdate'		=> gmdate('Y-m-d H:i:s'),
                ];

                if ($db->update($app['pp_schema'] . '.users',
                    $update,
                    ['id' => $id]))
                {
                    $user_cache_service->clear($id, $app['pp_schema']);
                    $user = $user_cache_service->get($id, $app['pp_schema']);
                    $alert_service->success('Paswoord opgeslagen.');

                    if (($user['status'] === 1 || $user['status'] === 2)
                        && $notify)
                    {
                        $to = $db->fetchColumn('select c.value
                            from ' . $app['pp_schema'] . '.contact c, ' .
                                $app['pp_schema'] . '.type_contact tc
                            where tc.id = c.id_type_contact
                                and tc.abbrev = \'mail\'
                                and c.id_user = ?', [$id]);

                        if ($to)
                        {
                            $vars = [
                                'user_id'		=> $id,
                                'password'		=> $password,
                            ];

                            $mail_queue->queue([
                                'schema'	=> $app['pp_schema'],
                                'to' 		=> $app['mail_addr_user']->get_active($id, $app['pp_schema']),
                                'reply_to'	=> $app['mail_addr_system']->get_support($app['pp_schema']),
                                'template'	=> 'password_reset/user',
                                'vars'		=> $vars,
                            ], 8000);

                            $alert_service->success('Notificatie mail verzonden');
                        }
                        else
                        {
                            $alert_service->warning('Geen E-mail adres bekend voor deze gebruiker, stuur het paswoord op een andere manier door!');
                        }
                    }

                    $link_render->redirect($app['r_users_show'], $app['pp_ary'], ['id' => $id]);
                }
                else
                {
                    $alert_service->error('Paswoord niet opgeslagen.');
                }
            }
            else
            {
                $alert_service->error($errors);
            }

        }

        $user = $user_cache_service->get($id, $app['pp_schema']);

        $assets_service->add([
            'generate_password.js',
        ]);

        $heading_render->add('Paswoord aanpassen');

        if ($app['pp_admin'] && $id !== $app['s_id'])
        {
            $heading_render->add(' voor ');
            $heading_render->add_raw($account_render->link($id, $app['pp_ary']));
        }

        $heading_render->fa('key');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="password" class="control-label">';
        $out .= 'Paswoord</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-key"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="password" name="password" ';
        $out .= 'value="';
        $out .= $password;
        $out .= '" required>';
        $out .= '<span class="input-group-btn">';
        $out .= '<button class="btn btn-default" type="button" ';
        $out .= 'data-generate-password>Genereer</button>';
        $out .= '</span>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="notify" class="control-label">';
        $out .= '<input type="checkbox" name="notify" id="notify"';
        $out .= $user['status'] == 1 || $user['status'] == 2 ? ' checked="checked"' : ' readonly';
        $out .= '>';
        $out .= ' Verzend notificatie E-mail met nieuw paswoord. ';

        if ($app['pp_admin'])
        {
            $out .= 'Dit is enkel mogelijk wanneer de Status ';
            $out .= 'actief is en E-mail adres ingesteld.';
        }

        $out .= '</label>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel($app['r_users_show'], $app['pp_ary'], ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Opslaan" name="zend" ';
        $out .= 'class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('users');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
