<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigNameCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\Annotation\Route;

class ConfigNameController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config',
        name: 'config_name',
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
        MenuService $menu_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $config_name_command = new ConfigNameCommand();

        $config_name_command->system_name = $config_service->get_str('system.name', $pp->schema());

        $builder = $this->createFormBuilder($config_name_command);
        $builder->add('system_name', TextType::class)
            ->add('submit', SubmitType::class);

        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $config_name_command = $form->getData();

            $config_service->set_str('system.name', $config_name_command->system_name, $pp->schema());

            $alert_service->success('Naam systeem aangepast.');
            $link_render->redirect('config_name', $pp->ary(), []);
        }

        $menu_service->set('config_name');

        return $this->render('config/config_name.html.twig', [
            'form'          => $form->createView(),
            'schema'        => $pp->schema(),
        ]);
    }
}
