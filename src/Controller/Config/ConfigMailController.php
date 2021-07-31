<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigMailCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\Annotation\Route;

class ConfigMailController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/mail',
        name: 'config_mail',
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
        $config_mail_command = new ConfigMailCommand();

        $config_mail_command->enabled = $config_service->get_bool('mail.enabled', $pp->schema());
        $config_mail_command->tag = $config_service->get_str('mail.tag', $pp->schema());

        $builder = $this->createFormBuilder($config_mail_command);
        $builder->add('enabled', CheckboxType::class)
            ->add('tag', TextType::class)
            ->add('submit', SubmitType::class);

        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $config_mail_command = $form->getData();

            $config_service->set_bool('mail.enabled', $config_mail_command->enabled, $pp->schema());
            $config_service->set_str('mail.tag', $config_mail_command->tag, $pp->schema());

            $alert_service->success('E-mail instellingen aangepast.');
            $this->redirectToRoute('config_mail', $pp->ary());
        }

        return $this->render('config/config_mail.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
