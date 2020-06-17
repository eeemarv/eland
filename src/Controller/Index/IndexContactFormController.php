<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Command\Index\IndexContactFormCommand;
use App\Form\Post\ContactForm\ContactFormType;
use App\Render\LinkRender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class IndexContactFormController extends AbstractController
{
    public function __invoke(
        Request $request,
        LinkRender $link_render,
        TranslatorInterface $translator,
        string $env_mail_hoster_address,
        string $env_mail_from_address,
        string $env_smtp_host,
        string $env_smtp_port,
        string $env_smtp_password,
        string $env_smtp_username
    ):Response
    {
        if ($request->isMethod('GET')
            && $request->query->has('ok'))
        {
            return $this->render('index/index_contact_form.html.twig', [
                'form_ok'   => true,
            ]);
        }

        $index_contact_form_command = new IndexContactFormCommand();

        $form = $this->createForm(ContactFormType::class,
                $index_contact_form_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $index_contact_form_command = $form->getData();
            $email = $index_contact_form_command->email;
            $message = $index_contact_form_command->message;

            $to = $env_mail_hoster_address;
            $from = $env_mail_from_address;

            if (!$to || !$from)
            {
                throw new HttpException(500, 'Interne configuratie fout.');
            }

            $text = $message . "\r\n\r\n\r\n" . 'browser: ';
            $text .= $request->headers->get('User-Agent') . "\n";

            $transport = (new \Swift_SmtpTransport($env_smtp_host, $env_smtp_port, 'tls'))
                ->setUsername($env_smtp_username)
                ->setPassword($env_smtp_password);
            $mailer = new \Swift_Mailer($transport);
            $mailer->registerPlugin(new \Swift_Plugins_AntiFloodPlugin(100, 30));

            $message = (new \Swift_Message())
                ->setSubject($translator->trans('index_contact_form.mail.subject'))
                ->setBody($text)
                ->setTo($to)
                ->setFrom($from)
                ->setReplyTo($email);

            $mailer->send($message);

            $link_render->redirect('index_contact_form', [], ['ok' => '1']);
        }

        return $this->render('index/index_contact_form.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
