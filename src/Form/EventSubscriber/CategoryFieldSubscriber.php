<?php declare(strict_types=1);

namespace App\Form\EventSubscriber;

use App\Form\Input\SelectAddonType;
use App\Repository\CategoryRepository;
use App\Service\PageParamsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class CategoryFieldSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected CategoryRepository $category_repository,
        protected PageParamsService $pp
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'pre_set_data',
        ];
    }

    public function pre_set_data(FormEvent $event)
    {
        $form = $event->getForm();

        $choices = array_merge(['' => ''],
            $this->category_repository->get_all_choices($this->pp->schema()));
        $options = [
            'choices'                   => $choices,
            'choice_translation_domain' => false,
            'attr'  => [
                'data-choiceloader'     => '#categories',
            ],
        ];

        $form->add('category_id', SelectAddonType::class, $options);
    }
}
