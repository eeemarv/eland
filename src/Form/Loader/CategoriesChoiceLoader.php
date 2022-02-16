<?php declare(strict_types=1);

namespace App\Form\Loader;

use App\Repository\CategoryRepository;
use App\Service\PageParamsService;
use Symfony\Component\Form\ChoiceList\Loader\AbstractChoiceLoader;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CategoriesChoiceLoader extends AbstractChoiceLoader implements ChoiceLoaderInterface
{
    protected $choice_list;

    public function __construct(
        protected bool $parent_selectable,
        protected bool $null_selectable,
        protected bool $all_choice,
        protected CategoryRepository $category_repository,
        protected PageParamsService $pp,
        protected TranslatorInterface $translator
    )
    {
    }

    public function loadChoices():iterable
    {
        $categories = $this->category_repository->get_all($this->pp->schema());
        $choices = [];

        if ($this->all_choice){
            $choices[$this->translator->trans('categories_select_type.all_choice')] = '';
        }

        if ($this->null_selectable){
            $choices[$this->translator->trans('categories_select_type.null_choice')] = 'null';
        }

        if ($this->parent_selectable)
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

        return $choices;
    }
}