<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigModulesCommand;
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

class ConfigModulesController extends AbstractController
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
        name: 'config_modules',
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
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $errors = [];

        $config_modules_command = new ConfigModulesCommand();

        foreach (self::CONFIG_MODULES as $key)
        {
            $prop = strtr($key, '.', '_');
            $config_modules_command->$prop = $config_service->get_bool($key, $pp->schema());
        }

        $builder = $this->createFormBuilder($config_modules_command);

        foreach (self::CONFIG_MODULES as $key)
        {
            $prop = strtr($key, '.', '_');
            $builder->add($prop, CheckboxType::class);
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
            $config_modules_command = $form->getData();

            foreach (self::CONFIG_MODULES as $key)
            {
                $prop = strtr($key, '.', '_');
                $config_service->set_bool($key, $config_modules_command->$prop, $pp->schema());
            }

            $alert_service->success('Modules aangepast.');
            $link_render->redirect('config_modules', $pp->ary(), []);
        }

        $menu_service->set('config_name');

        return $this->render('config/config_modules.html.twig', [
            'form'          => $form->createView(),
            'form_token'    => $form_token_service->get(),
            'schema'        => $pp->schema(),
        ]);
    }
}
