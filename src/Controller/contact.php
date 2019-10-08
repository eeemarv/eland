<?php declare(strict_types=1);

namespace App\Controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class contact
{
    public function contact(Request $request, app $app):Response
    {
        if (!$app['config']->get('contact_form_en', $app['pp_schema']))
        {
            $app['alert']->warning('De contactpagina is niet ingeschakeld.');
            $app['link']->redirect('login', $app['pp_ary'], []);
        }

        if($request->isMethod('POST'))
        {
            if (!$app['captcha']->validate())
            {
                $errors[] = 'De anti-spam verifiactiecode is niet juist ingevuld.';
            }

            $email = strtolower($request->request->get('email'));
            $message = $request->request->get('message');

            if (empty($email) || !$email)
            {
                $errors[] = 'Vul je E-mail adres in';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                $errors[] = 'Geen geldig E-mail adres';
            }

            if (empty($message) || strip_tags($message) == '' || !$message)
            {
                $errors[] = 'Geef een bericht in.';
            }

            if (!trim($app['config']->get('support', $app['pp_schema'])))
            {
                $errors[] = 'Het Support E-mail adres is niet ingesteld in dit Systeem';
            }

            if ($token_error = $app['form_token']->get_error())
            {
                $errors[] = $token_error;
            }

            if(!count($errors))
            {
                $contact = [
                    'message' 	=> $message,
                    'email'		=> $email,
                    'agent'		=> $request->headers->get('User-Agent'),
                    'ip'		=> $request->getClientIp(),
                ];

                $token = $app['data_token']->store($contact,
                    'contact', $app['pp_schema'], 86400);

                $app['monolog']->info('Contact form filled in with address ' .
                    $email . ' ' .
                    json_encode($contact),
                    ['schema' => $app['pp_schema']]);

                $app['queue.mail']->queue([
                    'schema'	=> $app['pp_schema'],
                    'to' 		=> [
                        $email => $email
                    ],
                    'template'	=> 'contact/confirm',
                    'vars'		=> [
                        'token' 	=> $token,
                    ],
                ], 10000);

                $app['alert']->success('Open je E-mailbox en klik
                    de link aan die we je zonden om je
                    bericht te bevestigen.');

                $app['link']->redirect('contact', $app['pp_ary'], []);
            }
            else
            {
                $app['alert']->error($errors);
            }
        }
        else
        {
            $message = '';
            $email = '';
        }

        $form_disabled = false;

        if (!$app['config']->get('mailenabled', $app['pp_schema']))
        {
            $app['alert']->warning('E-mail functies zijn
                uitgeschakeld door de beheerder.
                Je kan dit formulier niet gebruiken');

            $form_disabled = true;
        }
        else if (!$app['config']->get('support', $app['pp_schema']))
        {
            $app['alert']->warning('Er is geen support E-mail adres
                ingesteld door de beheerder.
                Je kan dit formulier niet gebruiken.');

            $form_disabled = true;
        }

        $app['heading']->add('Contact');
        $app['heading']->fa('comment-o');

        $top_text = $app['config']->get('contact_form_top_text', $app['pp_schema']);

        $out = $top_text ?: '';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="mail">';
        $out .= 'Je E-mail Adres';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-envelope-o"></i>';
        $out .= '</span>';
        $out .= '<input type="email" class="form-control" id="email" name="email" ';
        $out .= 'value="';
        $out .= $email;
        $out .= '" required';
        $out .= $form_disabled ? ' disabled' : '';
        $out .= '>';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Er wordt een validatielink die je moet ';
        $out .= 'aanklikken naar je E-mailbox verstuurd.';
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="message">Je Bericht</label>';
        $out .= '<textarea name="message" id="message" ';
        $out .= $form_disabled ? 'disabled ' : '';
        $out .= 'class="form-control" rows="4">';
        $out .= $message;
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= $app['captcha']->get_form_field();

        $out .= '<input type="submit" name="zend" ';
        $out .= $form_disabled ? 'disabled ' : '';
        $out .= 'value="Verzenden" class="btn btn-info btn-lg">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $bottom_text = $app['config']->get('contact_form_bottom_text', $app['pp_schema']);

        if ($bottom_text)
        {
            $out .= $bottom_text;
        }

        $out .= '<p>Leden: indien mogelijk, login en ';
        $out .= 'gebruik het Support formulier. ';
        $out .= '<i>Als je je paswoord kwijt bent ';
        $out .= 'kan je altijd zelf een nieuw paswoord ';
        $out .= 'aanvragen met je E-mail adres ';
        $out .= 'vanuit de login-pagina!</i></p>';

        $app['menu']->set('contact');

        return $app->render('base/sidebar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
