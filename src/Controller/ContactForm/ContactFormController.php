<?php declare(strict_types=1);

namespace App\Controller\ContactForm;

use App\Command\ContactForm\ContactFormCommand;
use App\Form\Post\ContactForm\ContactFormType;
use App\Queue\MailQueue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\PageParamsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContactFormController extends AbstractController
{
    public function __invoke(
        Request $request,
        LoggerInterface $logger,
        AlertService $alert_service,
        MenuService $menu_service,
        ConfigService $config_service,
        DataTokenService $data_token_service,
        LinkRender $link_render,
        PageParamsService $pp,
        MailQueue $mail_queue
    ):Response
    {
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

        $contact_form_command = new ContactFormCommand();

        $form_options = $form_disabled ? ['disabled' => true] : [];

        $form = $this->createForm(ContactFormType::class,
                $contact_form_command, $form_options)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid()
            && !$form_disabled)
        {
            $contact_form_command = $form->getData();
            $email = $contact_form_command->email;
            $message = $contact_form_command->message;

            $contact_form = [
                'message' 	=> $message,
                'email'		=> $email,
                'agent'		=> $request->headers->get('User-Agent'),
                'ip'		=> $request->getClientIp(),
            ];

            $token = $data_token_service->store($contact_form,
                'contact_form', $pp->schema(), 86400);

            $logger->info('Contact form filled in with address ' .
                $email . ' ' .
                json_encode($contact_form),
                ['schema' => $pp->schema()]);

            $mail_queue->queue([
                'schema'	=> $pp->schema(),
                'to' 		=> [
                    $email => $email
                ],
                'template'	=> 'contact_form/contact_form_confirm_request',
                'vars'		=> [
                    'token' 	=> $token,
                ],
            ], 10000);

            $alert_service->success('contact_form.success');
            $link_render->redirect('contact_form', $pp->ary(), []);
        }

        $menu_service->set('contact_form');

        return $this->render('contact_form/contact_form.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
