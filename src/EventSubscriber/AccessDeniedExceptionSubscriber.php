<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Render\LinkRender;
use App\Service\PageParamsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AccessDeniedExceptionSubscriber implements EventSubscriberInterface
{
    protected PageParamsService $pp;
    protected LinkRender $link_render;
    protected RequestStack $request_stack;

    public function __construct(
        RequestStack $request_stack,
        PageParamsService $pp,
        LinkRender $link_render
    )
    {
        $this->request_stack = $request_stack;
        $this->pp = $pp;
        $this->link_render = $link_render;
    }

    public function onExceptionEvent(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();

        if ($exception instanceof AccessDeniedException)
        {
            if ($this->pp->schema() !== '')
            {
                $request = $this->request_stack->getCurrentRequest();

                if ($this->pp->org_schema() === '')
                {
                    $this->link_render->redirect('login', [
                        'system' => $this->pp->system(),
                    ], [
                        'location'  => $request->getRequestUri(),
                    ]);
                }

                $this->link_render->redirect('login', [
                    'system' => $this->pp->org_system(),
                ], [
                    'location'  => $request->getRequestUri(),
                ]);
            }

            $this->link_render->redirect('index', [], []);
        }
    }

    public static function getSubscribedEvents():array
    {
        return [
            ExceptionEvent::class => ['onExceptionEvent', 1000],
        ];
    }
}
