<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Cnst\PagesCnst;
use App\Command\Index\IndexContactFormCommand;
use App\Form\Type\Index\IndexContactFormType;
use App\Service\AlertService;
use App\Service\DataTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
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
        Request $request,
        string $token,
        DataTokenService $data_token_service,
        AlertService $alert_service,
        MailerInterface $mailer,
        #[Autowire('%env(MAIL_HOSTER_ADDRESS)%')]
        string $env_mail_hoster_address,
        #[Autowire('%env(MAIL_FROM_ADDRESS)%')]
        string $env_mail_from_address,
    ):Response
    {
        if ($token !== PagesCnst::CMS_TOKEN)
        {
            $data = $data_token_service->retrieve($token, 'index_contact_form', null);

            if (!$data)
            {
                $alert_service->error('Ongeldig of verlopen token.');
                return $this->redirectToRoute('index_contact_form');
            }
        }

        /**
         * send emails
         */


        return $this->render('index/contact_confirm.html.twig');
    }
}
