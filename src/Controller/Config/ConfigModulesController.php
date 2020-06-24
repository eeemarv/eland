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

        $config_modules_command->forum_en = $config_service->get('forum_en', $pp->schema()) ? true : false;
        $config_modules_command->contact_form_en = $config_service->get('contact_form_en', $pp->schema()) ? true : false;
        $config_modules_command->register_en = $config_service->get('registration_en', $pp->schema()) ? true : false;

        $form = $this->createForm(ConfigModulesType::class,
                $config_modules_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $config_modules_command = $form->getData();

            $forum_en = $config_modules_command->forum_en;
            $contact_form_en = $config_modules_command->contact_form_en;
            $register_en = $config_modules_command->register_en;

            $config_service->set('forum_en', $pp->schema(), $forum_en ? '1' : '0');
            $config_service->set('contact_form_en', $pp->schema(), $contact_form_en ? '1' : '0');
            $config_service->set('registration_en', $pp->schema(), $register_en ? '1' : '0');

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
