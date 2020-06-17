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
    protected CategoryRepository $category_repository;
    protected PageParamsService $pp;

    public function __construct(
        CategoryRepository $category_repository,
        PageParamsService $pp
    )
    {
        $this->category_repository = $category_repository;
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
