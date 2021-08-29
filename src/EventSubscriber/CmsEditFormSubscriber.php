<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Command\Cms\CmsEditCommand;
use App\Form\Type\Cms\CmsEditType;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

class CmsEditFormSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected Db $db,
        protected Environment $twig,
        protected FormFactoryInterface $form_factory,
        protected PageParamsService $pp,
        protected SessionUserService $su,
        protected UserCacheService $user_cache_service,
        protected LinkRender $link_render,
        protected ConfigService $config_service
    )
    {
    }

    public function onKernelController(ControllerEvent $event):void
    {
        $request = $event->getRequest();

        if (!$request->isMethod('GET'))
        {
            return;
        }

        if ($request->isXmlHttpRequest())
        {
            return;
        }

        if (!$request->attributes->has('system'))
        {
            return;
        }

        if (!$this->pp->system())
        {
            return;
        }

        if (!$this->su->is_system_self())
        {
            return;
        }

        if (!$this->pp->edit_en())
        {
            return;
        }

        if (!($this->su->is_admin()))
        {
            return;
        }

        $route = $request->attributes->get('_route');
        $command = new CmsEditCommand();
        $command->route = $route;

        $cms_edit_form = $this->form_factory->create(CmsEditType::class, $command);

        $this->twig->addGlobal('cms_edit_form', $cms_edit_form->createView());
    }

    public static function getSubscribedEvents():array
    {
        return [
           KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
