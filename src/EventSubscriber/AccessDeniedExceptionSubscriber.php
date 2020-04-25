<?php

namespace App\EventSubscriber;

use App\Render\LinkRender;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AccessDeniedExceptionSubscriber implements EventSubscriberInterface
{
    protected LinkRender $link_render;
    protected RequestStack $request_stack;

    public function __construct(
        RequestStack $request_stack,
        LinkRender $link_render
    )
    {
        $this->request_stack = $request_stack;
        $this->link_render = $link_render;
    }

    public function onExceptionEvent(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();

        if ($exception instanceof AccessDeniedException)
        {
            $request = $this->request_stack->getCurrentRequest();

            $system = $request->attributes->get('system', '');

            if ($system)
            {
                $this->link_render->redirect('login', [
                    'system' => $system,
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
