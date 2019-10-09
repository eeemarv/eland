<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class PasswordResetController extends AbstractController
{
    public function password_reset(Request $request, app $app, Db $db):Response
    {
        if ($request->isMethod('POST'))
        {
            $email = $request->request->get('email');

            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
            }
            else if($email)
            {
                $user = $db->fetchAll('select u.id, u.name, u.letscode
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

                        $alert_service->success('Een link om je paswoord te resetten werd
                            naar je E-mailbox verzonden. Deze link blijft 24 uur geldig.');

                        $link_render->redirect('login', $app['pp_ary'], []);
                    }
                    else
                    {
                        $alert_service->error('E-Mail adres niet bekend');
                    }
                }
                else
                {
                    $alert_service->error('Het E-Mail adres is niet uniek in dit Systeem.');
                }
            }
            else
            {
                $alert_service->error('Geef een E-mail adres op');
            }
        }

        $heading_render->add('Paswoord vergeten');
        $heading_render->fa('key');

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
        $out .= 'Een link om je paswoord te resetten wordt naar je E-mailbox gestuurd.';
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<input type="submit" class="btn btn-info btn-lg" value="Reset paswoord" name="zend">';
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
