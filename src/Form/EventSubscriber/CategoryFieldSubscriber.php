<?php declare(strict_types=1);

namespace App\Form\EventSubscriber;

use App\Repository\CategoryRepository;
use App\Service\PageParamsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class CategoryFieldSubscriber implements EventSubscriberInterface
{
    protected array $type_options = [];

    public function __construct(
        protected CategoryRepository $category_repository,
        protected PageParamsService $pp
    )
    {
    }

    public static function getSubscribedEvents():array
    {
        return [
            FormEvents::PRE_SET_DATA => 'pre_set_data',
        ];
    }

    public function add(
        string $name,
        array $type_options = []
    ):void
    {
        $this->type_options[$name] = $type_options;
    }

    public function pre_set_data(FormEvent $event):void
    {
        $form = $event->getForm();

        $categories = $this->category_repository->get_all($this->pp->schema());

        foreach ($this->type_options as $name => $options)
        {
            $choices = [];

            if (isset($options['parent_selectable']) && $options['parent_selectable'])
            {
                foreach ($categories as $cat)
                {
                    $prefix = isset($cat['parent_id']) ?  '. > . ' : '';
                    $choices[$prefix . $cat['name']] = $cat['id'];
                }
            }
            else
            {
                $parent_name = '***';

                foreach ($categories as $cat)
                {
                    if (isset($cat['parent_id']))
                    {
                        if (!is_array($choices[$parent_name]))
                        {
                            $choices[$parent_name] = [];
                        }

                        $choices[$parent_name][$cat['name']] = $cat['id'];
                        continue;
                    }

                    $parent_name = $cat['name'];

                    if (isset($choices[$parent_name]))
                    {
                        error_log('Parent category already exists: ' . $cat['name'] . ', cat_id: ' . $cat['id']);
                    }

                    $choices[$parent_name] = $cat['id'];
                }
            }

            error_log(var_export($choices, true));

            $options = [
                'choices'                   => $choices,
                'choice_translation_domain' => false,
            ];

            $form->add($name, ChoiceType::class, $options);
        }
    }
}
