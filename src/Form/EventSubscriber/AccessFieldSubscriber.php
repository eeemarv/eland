<?php declare(strict_types=1);

namespace App\Form\EventSubscriber;

use App\Service\ItemAccessService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class AccessFieldSubscriber implements EventSubscriberInterface
{
    protected $item_access_service;

    public function __construct(
        ItemAccessService $item_access_service
    )
    {
        $this->item_access_service = $item_access_service;
    }

    public static function getSubscribedEvents()
    {
        return [
            FormEvents::PRE_SET_DATA => 'preSetData',
            FormEvents::PRE_SUBMIT => 'preSubmit',
        ];
    }

    public function preSetData(FormEvent $event)
    {
        $data = $event->getData();
        $data->access = 'user';

        /*
        $product = $event->getData();
        $form = $event->getForm();

        if (!$product || null === $product->getId()) {
            $form->add('name', TextType::class);
        }
        */
    }

    public function preSubmit(FormEvent $event)
    {
        $data = $event->getData();
    }
}