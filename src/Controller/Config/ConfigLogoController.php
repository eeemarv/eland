<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigNameCommand;
use App\Form\Post\Config\ConfigNameType;
use App\Form\Post\DelType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;

class ConfigLogoController extends AbstractController
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

        $form = $this->createForm(DelType::class)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $config_name_command = $form->getData();

            $system_name = $config_name_command->system_name;
            $email_tag = $config_name_command->email_tag;

            $config_service->set('systemname', $pp->schema(), $system_name);
            $config_service->set('systemtag', $pp->schema(), $email_tag);

            $alert_service->success('config_logo.success.del');
            $link_render->redirect('config_logo', $pp->ary(), []);
        }

        $menu_service->set('config');

        return $this->render('config/config_name.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
