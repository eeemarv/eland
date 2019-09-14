<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class password_reset
{
    public function password_reset(Request $request, app $app):Response
    {
        if ($request->isMethod('POST'))
        {
            $email = $request->request->get('email');

            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
            }
            else if($email)
            {
                $user = $app['db']->fetchAll('select u.id, u.name, u.letscode
                    from ' . $app['pp_schema'] . '.contact c, ' .
                        $app['pp_schema'] . '.type_contact tc, ' .
                        $app['pp_schema'] . '.users u
                    where c. value = ?
                        and tc.id = c.id_type_contact
                        and tc.abbrev = \'mail\'
                        and c.id_user = u.id
                        and u.status in (1, 2)', [$email]);

                if (count($user) < 2)
                {
                    $user = $user[0];

                    if ($user['id'])
                    {
                        $user_id = $user['id'];

                        $token = $app['data_token']->store([
                            'user_id'	=> $user_id,
                            'email'		=> $email,
                        ], 'password_reset', $app['pp_schema'], 86400);

                        $app['queue.mail']->queue([
                            'schema'	=> $app['pp_schema'],
                            'to' 		=> [$email => $user['letscode'] . ' ' . $user['name']],
                            'template'	=> 'password_reset/confirm',
                            'vars'		=> [
                                'token'			=> $token,
                                'user_id'		=> $user_id,
                            ],
                        ], 10000);

                        $app['alert']->success('Een link om je paswoord te resetten werd
                            naar je E-mailbox verzonden. Deze link blijft 24 uur geldig.');

                        $app['link']->redirect('login', $app['pp_ary'], []);
                    }
                    else
                    {
                        $app['alert']->error('E-Mail adres niet bekend');
                    }
                }
                else
                {
                    $app['alert']->error('Het E-Mail adres is niet uniek in dit Systeem.');
                }
            }
            else
            {
                $app['alert']->error('Geef een E-mail adres op');
            }
        }

        $app['heading']->add('Paswoord vergeten');
        $app['heading']->fa('key');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="email" class="control-label">Je E-mail adres</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-envelope-o"></i>';
        $out .= '</span>';
        $out .= '<input type="email" class="form-control" id="email" name="email" ';
        $out .= 'value="';
        $out .= $email ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Vul hier het E-mail adres in waarmee je geregistreerd staat in het Systeem. ';
        $out .= 'Een link om je paswoord te resetten wordt naar je E-mailbox verstuurd.';
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<input type="submit" class="btn btn-default" value="Reset paswoord" name="zend">';
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
