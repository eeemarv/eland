<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\HeadingRender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;

class ConfigExtModulesController extends AbstractController
{
    const CONFIG_MODULES =[
        'messages.enabled',
        'transactions.enabled',
        'news.enabled',
        'docs.enabled',
        'forum.enabled',
        'support_form.enabled',
        'home.menu.enabled',
        'contact_form.enabled',
        'register_form.enabled',
    ];

    #[Route(
        '/{system}/{role_short}/config/modules',
        name: 'config_ext_modules',
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
        HeadingRender $heading_render,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $errors = [];
        $form_data = [];

        foreach (self::CONFIG_MODULES as $key)
        {
            $name = strtr($key, '.', '_');
            $form_data[$name] = $config_service->get_bool($key, $pp->schema());
        }

        $builder = $this->createFormBuilder($form_data);

        foreach (self::CONFIG_MODULES as $key)
        {
            $name = strtr($key, '.', '_');
            $builder->add($name, CheckboxType::class);
        }

        $builder->add('submit', SubmitType::class);
        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($request->isMethod('POST'))
        {
            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (count($errors))
            {
                $alert_service->error($errors);
            }
        }

        if ($form->isSubmitted()
            && $form->isValid()
            && !count($errors))
        {
            $form_data = $form->getData();

            foreach (self::CONFIG_MODULES as $key)
            {
                $name = strtr($key, '.', '_');
                $config_service->set_bool($key, $form_data[$name], $pp->schema());
            }

            $alert_service->success('Modules aangepast.');
            $link_render->redirect('config_ext_modules', $pp->ary(), []);
        }

        $heading_render->add('Instellingen');
        $heading_render->fa('cogs');
        $menu_service->set('config');

        return $this->render('config_ext/config_ext_modules.html.twig', [
            'form'          => $form->createView(),
            'form_token'    => $form_token_service->get(),
            'schema'        => $pp->schema(),
        ]);
    }
}
