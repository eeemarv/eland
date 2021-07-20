<?php declare(strict_types=1);

namespace App\Controller\Config;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;

class ConfigDateFormatController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/date-format',
        name: 'config_date_format',
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
        DateFormatService $date_format_service,
        PageParamsService $pp
    ):Response
    {
        $date_format = $config_service->get_str('system.date_format', $pp->schema());

        $form_data = [
            'date_format'   => $date_format,
        ];

        $builder = $this->createFormBuilder($form_data);
        $builder->add('date_format', ChoiceType::class, [
            'choices'   => $date_format_service->get_choices(),
        ])
            ->add('submit', SubmitType::class);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $form_data = $form->getData();

            $config_service->set_str('system.date_format', $form_data['date_format'], $pp->schema());

            $alert_service->success('Datum- en tijdweergave aangepast.');
            $link_render->redirect('config_date_format', $pp->ary(), []);
        }

        $menu_service->set('config_name');

        return $this->render('config/config_date_format.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
