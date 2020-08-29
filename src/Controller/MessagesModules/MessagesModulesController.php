<?php declare(strict_types=1);

namespace App\Controller\MessagesModules;

use App\Command\MessagesModules\MessagesModulesCommand;
use App\Form\Post\MessagesModules\MessagesModulesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;

class MessagesModulesController extends AbstractController
{
    public function __invoke(
        Request $request,
        AlertService $alert_service,
        ConfigService $config_service,
        LinkRender $link_render,
        MenuService $menu_service,
        PageParamsService $pp
    ):Response
    {
        $messages_modules_command = new MessagesModulesCommand();

        $messages_modules_command->category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());
        $messages_modules_command->expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
        $messages_modules_command->units_enabled = $config_service->get_bool('messages.fields.units.enabled', $pp->schema());

        $form = $this->createForm(MessagesModulesType::class,
                $messages_modules_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $messages_modules_command = $form->getData();

            $category_enabled = $messages_modules_command->category_enabled;
            $expires_at_enabled = $messages_modules_command->expires_at_enabled;
            $units_enabled = $messages_modules_command->units_enabled;

            $config_service->set_bool('messages.fields.category.enabled', $category_enabled, $pp->schema());
            $config_service->set_bool('messages.fields.expires_at.enabled', $expires_at_enabled, $pp->schema());
            $config_service->set_bool('messages.fields.units.enabled', $units_enabled, $pp->schema());

            $alert_service->success('messages_modules.success');
            $link_render->redirect('messages_modules', $pp->ary(), []);
        }

        $menu_service->set('messages_modules');

        return $this->render('messages/modules/messages_modules.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
