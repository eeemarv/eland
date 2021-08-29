<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigMailAddrCommand;
use App\Form\Type\Config\ConfigMailAddrType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;

class ConfigMailAddrController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/mail-addr',
        name: 'config_mail_addr',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'config',
        ],
    )]

    public function __invoke(
        Request $request,
        AlertService $alert_service,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $command = new ConfigMailAddrCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(ConfigMailAddrType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('E-mail adressen aangepast.');
            return $this->redirectToRoute('config_mail_addr', $pp->ary());
        }

        return $this->render('config/config_mail_addr.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
