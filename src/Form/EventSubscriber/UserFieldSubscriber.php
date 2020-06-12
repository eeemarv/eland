<?php declare(strict_types=1);

namespace App\Form\EventSubscriber;

use App\Form\Input\Typeahead\TypeaheadAccountType;
use App\Service\PageParamsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class UserFieldSubscriber implements EventSubscriberInterface
{
    protected PageParamsService $pp;

    public function __construct(
        PageParamsService $pp
    )
    {
        $this->pp = $pp;
    }

    public static function getSubscribedEvents()
    {
        return [
            FormEvents::PRE_SET_DATA => 'pre_set_data',
        ];
    }

    public function pre_set_data(FormEvent $event)
    {
        if (!$this->pp->is_admin())
        {
            return;
        }

        $form = $event->getForm();

        $form->add('user_id', TypeaheadAccountType::class);
    }
}
