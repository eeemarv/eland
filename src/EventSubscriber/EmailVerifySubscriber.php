<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\EmailVerifyService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class EmailVerifySubscriber implements EventSubscriberInterface
{
    protected EmailVerifyService $email_verify_service;

    public function __construct(
        EmailVerifyService $email_verify_service
    )
    {
        $this->email_verify_service = $email_verify_service;
    }

    public function onKernelController(ControllerEvent $event):void
    {
        $request = $event->getRequest();

        if ($request->query->get('et') !== null)
        {
            $this->email_verify_service->verify($request->query->get('et'));
        }
    }

    public static function getSubscribedEvents():array
    {
        return [
           KernelEvents::CONTROLLER => ['onKernelController', 1000],
        ];
    }
}
