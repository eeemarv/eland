<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SupportController extends AbstractController
{
    public function support(Request $request, app $app):Response
    {
        if ($app['s_master'])
        {
            $user_email_ary = [];
        }
        else
        {
            $user_email_ary = $app['mail_addr_user']->get_active($app['s_id'], $app['pp_schema']);
        }

        $can_reply = count($user_email_ary) ? true : false;

        if ($request->isMethod('POST'))
        {
            $cc = $request->request->get('cc');
            $cc = isset($cc);
            $message = $request->request->get('message', '');
            $message = trim($message);

            if(empty($message) || strip_tags($message) == '' || $message === false)
            {
                $errors[] = 'Het bericht is leeg.';
            }

            if (!trim($app['config']->get('support', $app['pp_schema'])))
            {
                $errors[] = 'Het Support E-mail adres is niet ingesteld op dit Systeem';
            }

            if ($app['s_master'])
            {
                $errors[] = 'Het master account kan geen E-mail berichten versturen.';
            }

            if ($token_error = $app['form_token']->get_error())
            {
                $errors[] = $token_error;
            }

            if(!count($errors))
            {
                $vars = [
                    'user_id'	=> $app['s_id'],
                    'can_reply'	=> $can_reply,
                    'message'	=> $message,
                ];

                if ($cc && $can_reply)
                {
                    $app['queue.mail']->queue([
                        'schema'	=> $app['pp_schema'],
                        'template'	=> 'support/copy',
                        'vars'		=> $vars,
                        'to'		=> $user_email_ary,
                    ], 8500);
                }

                $app['queue.mail']->queue([
                    'schema'	=> $app['pp_schema'],
                    'template'	=> 'support/support',
                    'vars'		=> $vars,
                    'to'		=> $app['mail_addr_system']->get_support($app['pp_schema']),
                    'reply_to'	=> $user_email_ary,
                ], 8000);

                $app['alert']->success('De Support E-mail is verzonden.');
                $app['link']->redirect($app['r_default'], $app['pp_ary'], []);
            }
            else
            {
                $app['alert']->error($errors);
            }
        }
        else
        {
            $message = '';

            if ($app['s_master'])
            {
                $app['alert']->warning('Het master account kan geen E-mail berichten versturen.');
            }
            else
            {
                if (!$can_reply)
                {
                    $app['alert']->warning('Je hebt geen E-mail adres ingesteld voor je account. ');
                }
            }

            $cc = true;
        }

        if (!$can_reply)
        {
            $cc = false;
        }

        if (!$app['config']->get('mailenabled', $app['pp_schema']))
        {
            $app['alert']->warning('De E-mail functies zijn uitgeschakeld door de beheerder. Je kan dit formulier niet gebruiken');
        }
        else if (!$app['config']->get('support', $app['pp_schema']))
        {
            $app['alert']->warning('Er is geen Support E-mail adres ingesteld door de beheerder. Je kan dit formulier niet gebruiken.');
        }

        $app['heading']->add('Help / Probleem melden');
        $app['heading']->fa('ambulance');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="message">Je Bericht</label>';
        $out .= '<textarea name="message" ';
        $out .= 'class="form-control" id="message" rows="4"';
        $out .= $app['s_master'] ? ' disabled' : '';
        $out .= '>';
        $out .= $message;
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= '<div class="form-group';
        $out .= $can_reply ? '' : ' checkbox disabled has-warning';
        $out .= '">';
        $out .= '<label for="cc" class="control-label">';
        $out .= '<input type="checkbox" name="cc" ';
        $out .= $can_reply ? '' : 'disabled ';
        $out .= 'id="cc" value="1"';
        $out .= $cc ? ' checked="checked"' : '';
        $out .= '> ';

        if ($can_reply)
        {
            $out .= 'Stuur een kopie naar mijzelf.';
        }
        else
        {
            $out .= 'Een kopie van je bericht naar ';
            $out .= 'jezelf sturen is ';
            $out .= 'niet mogelijk want er is ';
            $out .= 'geen E-mail adres ingesteld voor ';
            $out .= 'je account.';
        }

        $out .= '</label>';
        $out .= '</div>';

        $out .= '<input type="submit" name="zend" value="Verzenden" class="btn btn-info btn-lg">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('support');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}