<?php declare(strict_types=1);

namespace App\Controller\Support;

use App\Command\Support\SupportCommand;
use App\Form\Post\Support\SupportType;
use App\Queue\MailQueue;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SupportController extends AbstractController
{
    public function __invoke(
        Request $request,
        AlertService $alert_service,
        MenuService $menu_service,
        ConfigService $config_service,
        LinkRender $link_render,
        MailQueue $mail_queue,
        MailAddrUserService $mail_addr_user_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MailAddrSystemService $mail_addr_system_service
    ):Response
    {
        $user_email_ary = $mail_addr_user_service->get_active($su->id(), $pp->schema());
        $can_reply = count($user_email_ary) ? true : false;

        $form_disabled = false;

        if (!$config_service->get_bool('mail.enabled', $pp->schema()))
        {
            $alert_service->warning('email.warning.disabled');
            $form_disabled = true;
        }
        else if (!$config_service->get_ary('mail.addresses.support', $pp->schema()))
        {
            $alert_service->warning('email.warning.no_support_address');
            $form_disabled = true;
        }
        else if ($su->is_master())
        {
            $alert_service->warning('email.warning.master_not_allowed');
            $form_disabled = true;
        }

        $support_command = new SupportCommand();

        $form_options = $form_disabled ? ['disabled' => true] : [];

        $form = $this->createForm(SupportType::class,
                $support_command, $form_options)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid()
            && !$form_disabled)
        {
            $support_command = $form->getData();
            $message = $support_command->message;
            $cc = $support_command->cc;

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

            $alert_service->success('support.success');
            $link_render->redirect($vr->get('default'), $pp->ary(), []);
        }

        $menu_service->set('support');

        return $this->render('support/support.html.twig', [
            'form'      => $form->createView(),
            'can_reply' => $can_reply,
            'schema'    => $pp->schema(),
        ]);
    }
}
