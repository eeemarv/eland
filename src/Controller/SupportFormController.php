<?php declare(strict_types=1);

namespace App\Controller;

use App\Cnst\BulkCnst;
use App\Queue\MailQueue;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class SupportFormController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/support',
        name: 'support_form',
        methods: ['GET', 'POST'],
        priority: 20,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'support_form',
        ],
    )]

    public function __invoke(
        Request $request,
        AlertService $alert_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        MailQueue $mail_queue,
        MailAddrUserService $mail_addr_user_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MailAddrSystemService $mail_addr_system_service
    ):Response
    {
        if (!$config_service->get_bool('support_form.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Support form not enabled.');
        }

        $errors = [];

        if ($su->is_master())
        {
            $user_email_ary = [];
        }
        else
        {
            $user_email_ary = $mail_addr_user_service->get_active($su->id(), $pp->schema());
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

            if (!$config_service->get_ary('mail.addresses.support', $pp->schema()))
            {
                $errors[] = 'Het Support E-mail adres is niet ingesteld op dit Systeem';
            }

            if ($su->is_master())
            {
                $errors[] = 'Het master account kan geen E-mail berichten versturen.';
            }

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if(!count($errors))
            {
                $vars = [
                    'user_id'	=> $su->id(),
                    'can_reply'	=> $can_reply,
                    'message'	=> $message,
                ];

                if ($cc && $can_reply)
                {
                    $mail_queue->queue([
                        'schema'	=> $pp->schema(),
                        'template'	=> 'support/copy',
                        'vars'		=> $vars,
                        'to'		=> $user_email_ary,
                    ], 8500);
                }

                $mail_queue->queue([
                    'schema'	=> $pp->schema(),
                    'template'	=> 'support/support',
                    'vars'		=> $vars,
                    'to'		=> $mail_addr_system_service->get_support($pp->schema()),
                    'reply_to'	=> $user_email_ary,
                ], 8000);

                $alert_service->success('De Support E-mail is verzonden.');
                $this->redirectToRoute($vr->get('default'), $pp->ary());
            }
            else
            {
                $alert_service->error($errors);
            }
        }
        else
        {
            $message = '';

            if ($su->is_master())
            {
                $alert_service->warning('Het master account kan geen E-mail berichten versturen.');
            }
            else
            {
                if (!$can_reply)
                {
                    $alert_service->warning('Je hebt geen E-mail adres ingesteld voor je account. ');
                }
            }

            $cc = true;
        }

        if (!$can_reply)
        {
            $cc = false;
        }

        if (!$config_service->get_bool('mail.enabled', $pp->schema()))
        {
            $alert_service->warning('De E-mail functies zijn uitgeschakeld door de beheerder. Je kan dit formulier niet gebruiken');
        }
        else if (!$config_service->get_ary('mail.addresses.support', $pp->schema()))
        {
            $alert_service->warning('Er is geen Support E-mail adres ingesteld door de beheerder. Je kan dit formulier niet gebruiken.');
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="message">Je Bericht</label>';
        $out .= '<textarea name="message" ';
        $out .= 'class="form-control" id="message" rows="4"';
        $out .= $su->is_master() ? ' disabled' : '';
        $out .= '>';
        $out .= $message;
        $out .= '</textarea>';
        $out .= '</div>';

        if ($can_reply)
        {
            $cc_lbl = 'Stuur een kopie naar mijzelf.';
        }
        else
        {
            $cc_lbl = 'Een kopie van je bericht naar ';
            $cc_lbl .= 'jezelf sturen is ';
            $cc_lbl .= 'niet mogelijk want er is ';
            $cc_lbl .= 'geen E-mail adres ingesteld voor ';
            $cc_lbl .= 'je account.';
        }

        $cc_attr = $can_reply ? '' : ' disabled';
        $cc_attr .= $cc ? ' checked' : '';

        $out .= strtr(BulkCnst::TPL_CHECKBOX_DIV_ATTR, [
            '%div_attr%'    => $can_reply ? '' : ' checkbox disabled has-warning',
            '%name%'        => 'cc',
            '%label%'       => $cc_lbl,
            '%attr%'        => $cc_attr,
        ]);

        $out .= '<input type="submit" name="zend" value="Verzenden" class="btn btn-info btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        return $this->render('support_form/support_form.html.twig', [
            'content'   => $out,
        ]);
    }
}
