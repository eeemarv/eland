<?php declare(strict_types=1);

namespace App\Controller\SupportForm;

use App\Command\SupportForm\SupportFormCommand;
use App\Form\Type\SupportForm\SupportFormType;
use App\Queue\MailQueue;
use App\Service\AlertService;
use App\Service\ConfigService;
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

        $is_master = $su->is_master();
        $mail_enabled = $config_service->get_bool('mail.enabled', $pp->schema());
        $support_addr = $mail_addr_system_service->get_support($pp->schema());
        $form_disabled = !$mail_enabled || count($support_addr) < 1 || $is_master;

        $user_email_ary = $is_master ? [] : $mail_addr_user_service->get_active($su->id(), $pp->schema());
        $can_reply = count($user_email_ary) > 0;

        $command = new SupportFormCommand();
        $command->cc = true;

        $form_options = [
            'validation_groups'     => ['send'],
            'disabled'  => $form_disabled,
        ];

        $form = $this->createForm(SupportFormType::class, $command, $form_options);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid()
            && !$form_disabled)
        {
            $command = $form->getData();

            $vars = [
                'user_id'	=> $su->id(),
                'can_reply'	=> $can_reply,
                'message'	=> $command->message,
            ];

            if ($command->cc && $can_reply)
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
                'to'		=> $support_addr,
                'reply_to'	=> $user_email_ary,
            ], 8000);

            $alert_service->success('De Support E-mail is verzonden.');
            return $this->redirectToRoute($vr->get('default'), $pp->ary());
        }

        if ($is_master)
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

        if (!$mail_enabled)
        {
            $alert_service->warning('De E-mail functies zijn uitgeschakeld door de beheerder. Je kan dit formulier niet gebruiken');
        }
        else if (count($support_addr) < 1)
        {
            $alert_service->warning('Er is geen Support E-mail adres ingesteld door de beheerder. Je kan dit formulier niet gebruiken.');
        }

        return $this->render('support_form/support_form.html.twig', [
            'form'      => $form->createView(),
            'can_reply' => $can_reply,
        ]);
    }
}
