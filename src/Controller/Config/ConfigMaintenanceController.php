<?php declare(strict_types=1);

namespace App\Controller\Config;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;

class ConfigMaintenanceController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/maintenance',
        name: 'config_maintenance',
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
        $maintenance_en = $config_service->get_bool('system.maintenance_en', $pp->schema());

        $form_data = [
            'maintenance_en'  => $maintenance_en,
        ];

        $builder = $this->createFormBuilder($form_data);
        $builder->add('maintenance_en', CheckboxType::class)
            ->add('submit', SubmitType::class);

        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $form_data = $form->getData();

            $config_service->set_bool('system.maintenance_en', $form_data['maintenance_en'], $pp->schema());

            $alert_service->success('Onderhouds modus aangepast.');
            $link_render->redirect('config_maintenance', $pp->ary(), []);
        }

        $menu_service->set('config_name');

        return $this->render('config/config_maintenance.html.twig', [
            'form'          => $form->createView(),
            'schema'        => $pp->schema(),
        ]);
    }
}
