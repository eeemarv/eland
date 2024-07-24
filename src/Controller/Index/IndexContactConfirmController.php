<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Service\DataTokenService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class IndexContactConfirmController extends AbstractController
{
    #[Route(
        '/contact/{token}',
        name: 'index_contact_confirm',
        methods: ['GET'],
        requirements: [
            'token'         => '%assert.token%',
        ]
    )]

    public function __invoke(
        string $token,
        DataTokenService $data_token_service,
        MailerInterface $mailer,
        #[Autowire('%env(MAIL_HOSTER_ADDRESS)%')]
        string $env_mail_hoster_address,
        #[Autowire('%env(MAIL_FROM_ADDRESS)%')]
        string $env_mail_from_address,
    ):Response
    {
        $data = $data_token_service->retrieve($token, 'index_contact_form', null);

        if (!$data)
        {
            $this->addFlash('alert', [
                'type'      => 'error',
                'message'   => 'Ongeldig of verlopen token.',
            ]);
            return $this->redirectToRoute('index_contact');
        }

        $data_token_service->del($token, 'index_contact_form', null);

        $email = new TemplatedEmail();
        $email->from(new Address($env_mail_from_address, 'eLAND contact'));
        $email->to(new Address($env_mail_hoster_address));
        $email->subject('Bericht van eLAND contactformulier');
        $email->htmlTemplate('@email/index/index_contact.html.twig');
        $email->context($data);
        $email->replyTo(new Address($data['email_address']));
        $mailer->send($email);

        return $this->render('index/contact_confirm.html.twig');
    }
}
