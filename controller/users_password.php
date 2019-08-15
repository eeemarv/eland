<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class users_password
{
    public function users_password(Request $request, app $app):Response
    {
        return $this->form_admin($request, $app, $app['s_id']);
    }

    public function users_password_admin(Request $request, app $app, int $id):Response
    {
        $password = trim($request->request->get('password', ''));
        $notify = $request->request->get('notify', '');

        if($request->isMethod('POST'))
        {
            if ($password === '')
            {
                $errors[] = 'Vul paswoord in!';
            }

            if (!$app['s_admin']
                && $app['password_strength']->get($password) < 50)
            {
                $errors[] = 'Te zwak paswoord.';
            }

            if ($error_token = $app['form_token']->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $update = [
                    'password'	=> hash('sha512', $password),
                    'mdate'		=> gmdate('Y-m-d H:i:s'),
                ];

                if ($app['db']->update($app['tschema'] . '.users',
                    $update,
                    ['id' => $id]))
                {
                    $app['user_cache']->clear($id, $app['tschema']);
                    $user = $app['user_cache']->get($id, $app['tschema']);
                    $app['alert']->success('Paswoord opgeslagen.');

                    if (($user['status'] === 1 || $user['status'] === 2)
                        && $notify)
                    {
                        $to = $app['db']->fetchColumn('select c.value
                            from ' . $app['tschema'] . '.contact c, ' .
                                $app['tschema'] . '.type_contact tc
                            where tc.id = c.id_type_contact
                                and tc.abbrev = \'mail\'
                                and c.id_user = ?', [$id]);

                        if ($to)
                        {
                            $vars = [
                                'user_id'		=> $id,
                                'password'		=> $password,
                            ];

                            $app['queue.mail']->queue([
                                'schema'	=> $app['tschema'],
                                'to' 		=> $app['mail_addr_user']->get($id, $app['tschema']),
                                'reply_to'	=> $app['mail_addr_system']->get_support($app['tschema']),
                                'template'	=> 'password_reset/user',
                                'vars'		=> $vars,
                            ], 8000);

                            $app['alert']->success('Notificatie mail verzonden');
                        }
                        else
                        {
                            $app['alert']->warning('Geen E-mail adres bekend voor deze gebruiker, stuur het paswoord op een andere manier door!');
                        }
                    }

                    $app['link']->redirect($app['r_users_show'], $app['pp_ary'], ['id' => $id]);
                }
                else
                {
                    $app['alert']->error('Paswoord niet opgeslagen.');
                }
            }
            else
            {
                $app['alert']->error($errors);
            }

        }

        $user = $app['user_cache']->get($id, $app['tschema']);

        $app['assets']->add([
            'generate_password.js',
        ]);

        $app['heading']->add('Paswoord aanpassen');

        if ($app['s_admin'] && $id !== $app['s_id'])
        {
            $app['heading']->add(' voor ');
            $app['heading']->add($app['account']->link($id, $app['pp_ary']));
        }

        $app['heading']->fa('key');

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
        $out .= 'id="generate">Genereer</button>';
        $out .= '</span>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="notify" class="control-label">';
        $out .= '<input type="checkbox" name="notify" id="notify"';
        $out .= $user['status'] == 1 || $user['status'] == 2 ? ' checked="checked"' : ' readonly';
        $out .= '>';
        $out .= ' Verzend notificatie E-mail met nieuw paswoord. ';
        $out .= 'Dit is enkel mogelijk wanneer de Status ';
        $out .= 'actief is en E-mail adres ingesteld.';
        $out .= '</label>';
        $out .= '</div>';

        $out .= $app['link']->btn_cancel($app['r_users_show'], $app['pp_ary'], ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Opslaan" name="zend" ';
        $out .= 'class="btn btn-primary">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('users');

        return $app['tpl']->get();
    }
}
