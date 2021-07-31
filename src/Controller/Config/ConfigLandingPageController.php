<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Cnst\ConfigCnst;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;

class ConfigLandingPageController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/landing-page',
        name: 'config_landing_page',
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
        $choices = [];

        $landing_page = $config_service->get_str('system.default_landing_page', $pp->schema());

        foreach(ConfigCnst::LANDING_PAGE_OPTIONS as $opt => $lang)
        {
            $choices[$opt . '.title'] = $opt;
        }

        $form_data = [
            'landing_page'   => $landing_page,
        ];

        $builder = $this->createFormBuilder($form_data);
        $builder->add('landing_page', ChoiceType::class, [
            'choices'   => $choices,
        ])
            ->add('submit', SubmitType::class);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $form_data = $form->getData();

            $config_service->set_str('system.default_landing_page', $form_data['landing_page'], $pp->schema());

            $alert_service->success('Landingspagina aangepast.');
            $this->redirectToRoute('config_landing_page', $pp->ary());
        }

        return $this->render('config/config_landing_page.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
