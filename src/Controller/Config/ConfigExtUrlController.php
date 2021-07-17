<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigExtUrlCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Routing\Annotation\Route;

class ConfigExtUrlController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/ext-url',
        name: 'config_ext_url',
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
        $config_ext_url_command = new ConfigExtUrlCommand();
        $config_ext_url_command->url = $config_service->get_str('system.website_url', $pp->schema());

        $builder = $this->createFormBuilder($config_ext_url_command);
        $builder->add('url', UrlType::class)
            ->add('submit', SubmitType::class);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $config_ext_url_command = $form->getData();

            $config_service->set_str('system.website_url', $config_ext_url_command->url ?? '', $pp->schema());

            $alert_service->success('Externe URL aangepast.');
            $link_render->redirect('config_ext_url', $pp->ary(), []);
        }

        $menu_service->set('config_name');

        return $this->render('config/config_ext_url.html.twig', [
            'form'          => $form->createView(),
            'schema'        => $pp->schema(),
        ]);
    }
}
