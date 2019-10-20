<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\EmailValidateService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class EmailValidateSubscriber implements EventSubscriberInterface
{
    protected $email_validate_service;

    public function __construct(
        EmailValidateService $email_validate_service
    )
    {
        $this->email_validate_service = $email_validate_service;
    }

    public function onKernelController(ControllerEvent $event)
    {
        $request = $event->getRequest();

        if ($request->query->get('et') !== null)
        {
            $this->email_validate_service->validate($request->query->get('et'));
        }
    }

    public static function getSubscribedEvents()
    {
        return [
           'kernel.controller' => ['onKernelController', 1000],
        ];
    }
}
