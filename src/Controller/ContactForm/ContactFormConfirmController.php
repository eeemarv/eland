<?php declare(strict_types=1);

namespace App\Controller\ContactForm;

use App\Queue\MailQueue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Render\LinkRender;
use App\Service\DataTokenService;
use App\Service\MailAddrSystemService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContactFormConfirmController extends AbstractController
{
    public function __invoke(
        string $token,
        LinkRender $link_render,
        AlertService $alert_service,
        DataTokenService $data_token_service,
        MailAddrSystemService $mail_addr_system_service,
        PageParamsService $pp,
        MailQueue $mail_queue
    ):Response
    {
        $data = $data_token_service->retrieve($token, 'contact_form', $pp->schema());

        if (!$data)
        {
            $alert_service->error('contact_form_confirm.error.token_not_valid');
            $link_render->redirect('contact_form', $pp->ary(), []);
        }

        $vars = [
            'message'		=> $data['message'],
            'ip'			=> $data['ip'],
            'agent'			=> $data['agent'],
            'email'			=> $data['email'],
        ];

        $mail_queue->queue([
            'schema'	=> $pp->schema(),
            'template'	=> 'contact_form/contact_form_copy',
            'vars'		=> $vars,
            'to'		=> [$data['email'] => $data['email']],
        ], 9000);

        $mail_queue->queue([
            'schema'	=> $pp->schema(),
            'template'	=> 'contact_form/contact_form_support',
            'vars'		=> $vars,
            'to'		=> $mail_addr_system_service->get_support($pp->schema()),
            'reply_to'	=> [$data['email']],
        ], 8000);

        $data_token_service->del($token, 'contact_form', $pp->schema());

        $alert_service->success('contact_form_confirm.success.message_sent');
        $link_render->redirect('contact_form', $pp->ary(), []);

        return new Response();
    }
}
