<?php declare(strict_types=1);

namespace App\Controller\MessagesModules;

use App\Command\MessagesModules\MessagesCleanupCommand;
use App\Form\Post\MessagesModules\MessagesCleanupType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MessagesCleanupController extends AbstractController
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
        if (!$config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages cleanup module not enabled.');
        }

        $messages_cleanup_command = new MessagesCleanupCommand();
        $messages_cleanup_command->cleanup_enabled = $config_service->get_bool('messages.cleanup.enabled', $pp->schema());
        $messages_cleanup_command->cleanup_after_days = $config_service->get_int('messages.cleanup.after_days', $pp->schema());
        $messages_cleanup_command->expires_at_days_default = $config_service->get_int('messages.fields.expires_at.days_default', $pp->schema());
        $messages_cleanup_command->expires_at_required = $config_service->get_bool('messages.fields.expires_at.required', $pp->schema());
        $messages_cleanup_command->expires_at_switch_enabled = $config_service->get_bool('messages.fields.expires_at.switch_enabled', $pp->schema());
        $messages_cleanup_command->expire_notify = $config_service->get_bool('messages.expire.notify', $pp->schema());

        $form = $this->createForm(MessagesCleanupType::class,
                $messages_cleanup_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $messages_cleanup_command = $form->getData();

            $cleanup_enabled = $messages_cleanup_command->cleanup_enabled;
            $cleanup_after_days = $messages_cleanup_command->cleanup_after_days;
            $expires_at_days_default = $messages_cleanup_command->expires_at_days_default;
            $expires_at_required = $messages_cleanup_command->expires_at_required;
            $expires_at_switch_enabled = $messages_cleanup_command->expires_at_switch_enabled;
            $expire_notify = $messages_cleanup_command->expire_notify;

            $config_service->set_bool('messages.cleanup.enabled', $cleanup_enabled, $pp->schema());
            $config_service->set_int('messages.cleanup.after_days', (int) $cleanup_after_days, $pp->schema());
            $config_service->set_int('messages.fields.expires_at.days_default', (int) $expires_at_days_default, $pp->schema());
            $config_service->set_bool('messages.fields.expires_at.required', $expires_at_required, $pp->schema());
            $config_service->set_bool('messages.fields.expires_at.switch_enabled', $expires_at_switch_enabled, $pp->schema());
            $config_service->set_bool('messages.expire.notify', $expire_notify, $pp->schema());

            $alert_service->success('messages_cleanup.success');
            $link_render->redirect('messages_cleanup', $pp->ary(), []);
        }

        $menu_service->set('messages_cleanup');

        return $this->render('messages/modules/messages_cleanup.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
