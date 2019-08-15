<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class register
{
    public function register(Request $request, app $app):Response
    {
        if (!$app['config']->get('registration_en', $app['tschema']))
        {
            $app['alert']->warning('De inschrijvingspagina is niet ingeschakeld.');
            $app['link']->redirect('login', $app['pp_ary'], []);
        }

        if ($request->isMethod('POST'))
        {
            $reg = [
                'email'			=> $request->request->get('email', ''),
                'first_name'	=> $request->request->get('first_name', ''),
                'last_name'		=> $request->request->get('last_name', ''),
                'postcode'		=> $request->request->get('postcode', ''),
                'tel'			=> $request->request->get('tel', ''),
                'gsm'			=> $request->request->get('gsm', ''),
            ];

            $app['monolog']->info('Registration request for ' .
                $reg['email'], ['schema' => $app['tschema']]);

            if(!$reg['email'])
            {
                $app['alert']->error('Vul een E-mail adres in.');
            }
            else if (!$app['captcha']->validate())
            {
                $app['alert']->error('De anti-spam verifiactiecode is niet juist ingevuld.');
            }
            else if (!filter_var($reg['email'], FILTER_VALIDATE_EMAIL))
            {
                $app['alert']->error('Geen geldig E-mail adres.');
            }
            else if ($app['db']->fetchColumn('select c.id_user
                from ' . $app['tschema'] . '.contact c, ' .
                    $app['tschema'] . '.type_contact tc
                where c. value = ?
                    AND tc.id = c.id_type_contact
                    AND tc.abbrev = \'mail\'', [$reg['email']]))
            {
                $app['alert']->error('Er bestaat reeds een inschrijving
                    met dit E-mail adres.');
            }
            else if (!$reg['first_name'])
            {
                $app['alert']->error('Vul een Voornaam in.');
            }
            else if (!$reg['last_name'])
            {
                $app['alert']->error('Vul een Achternaam in.');
            }
            else if (!$reg['postcode'])
            {
                $app['alert']->error('Vul een Postcode in.');
            }
            else if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
            }
            else
            {
                $token = $app['data_token']->store($reg,
                    'register', $app['tschema'], 604800); // 1 week

                $app['queue.mail']->queue([
                    'schema'	=> $app['tschema'],
                    'to' 		=> [$reg['email'] => $reg['first_name'] . ' ' . $reg['last_name']],
                    'vars'		=> ['token' => $token],
                    'template'	=> 'register/confirm',
                ], 10000);

                $app['alert']->success('Open je E-mailbox en klik op de
                    bevestigingslink in de E-mail die we naar je gestuurd
                    hebben om je inschrijving te voltooien.');

                $app['link']->redirect('login', $app['pp_ary'], []);
            }
        }

        $app['heading']->add('Inschrijven');
        $app['heading']->fa('check-square-o');

        $top_text = $app['config']->get('registration_top_text', $app['tschema']);

        $out = $top_text ?: '';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="first_name" class="control-label">Voornaam*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="first_name" name="first_name" ';
        $out .= 'value="';
        $out .= $reg['first_name'] ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="last_name" class="control-label">Achternaam*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="last_name" name="last_name" ';
        $out .= 'value="';
        $out .= $reg['last_name'] ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="email" class="control-label">E-mail*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-envelope-o"></i>';
        $out .= '</span>';
        $out .= '<input type="email" class="form-control" id="email" name="email" ';
        $out .= 'value="';
        $out .= $reg['email'] ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="postcode" class="control-label">Postcode*</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-map-marker"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="postcode" name="postcode" ';
        $out .= 'value="';
        $out .= $reg['postcode'] ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="gsm" class="control-label">Gsm</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-mobile"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="gsm" name="gsm" ';
        $out .= 'value="';
        $out .= $reg['gsm'] ?? '';
        $out .=  '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="tel" class="control-label">Telefoon</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-phone"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="tel" name="tel" ';
        $out .= 'value="';
        $out .= $reg['tel'] ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $app['captcha']->get_form_field();

        $out .= '<input type="submit" class="btn btn-default" value="Inschrijven" name="zend">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $bottom_text = $app['config']->get('registration_bottom_text', $app['tschema']);

        $out .= $bottom_text ?: '';

        $app['tpl']->add($out);
        $app['tpl']->menu('register');

        return $app['tpl']->get();
    }
}
