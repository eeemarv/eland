<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigModulesCommand;
use App\Form\Post\Config\ConfigModulesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;

class ConfigModulesController extends AbstractController
{
    public function __invoke(
        Request $request,
        MenuService $menu_service,
        AlertService $alert_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $config_modules_command = new ConfigModulesCommand();

        $config_modules_command->forum_enabled = $config_service->get_bool('forum.enabled', $pp->schema());
        $config_modules_command->contact_form_enabled = $config_service->get_bool('contact_form.enabled', $pp->schema());
        $config_modules_command->register_form_enabled = $config_service->get_bool('register_form.enabled', $pp->schema());

        $form = $this->createForm(ConfigModulesType::class,
                $config_modules_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $config_modules_command = $form->getData();

            $forum_enabled = $config_modules_command->forum_enabled;
            $contact_form_enabled = $config_modules_command->contact_form_enabled;
            $register_form_enabled = $config_modules_command->register_form_enabled;

            $config_service->set_bool('forum.enabled', $forum_enabled, $pp->schema());
            $config_service->set_bool('contact_form.enabled', $contact_form_enabled, $pp->schema());
            $config_service->set_bool('register_form.enabled', $register_form_enabled, $pp->schema());

            $alert_service->success('config_modules.success');
            $link_render->redirect('config_modules', $pp->ary(), []);
        }

        $menu_service->set('config');

        return $this->render('config/config_modules.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
