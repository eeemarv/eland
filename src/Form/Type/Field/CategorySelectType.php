<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use App\Form\Loader\CategoriesChoiceLoader;
use App\Repository\CategoryRepository;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\Options;

class CategorySelectType extends AbstractType
{
    public function __construct(
        protected CategoryRepository $category_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('parent_selectable', false);
        $resolver->setAllowedTypes('parent_selectable', 'bool');

        $resolver->setDefault('choice_loader', function (Options $options){
            return ChoiceList::loader($this,
                new CategoriesChoiceLoader($options['parent_selectable'], $this->category_repository, $this->pp));
        });
    }

    private static function get_choices(
        CategoryRepository $category_repository,
        PageParamsService $pp
    )
    {
        $choices = [];
        $categories = $category_repository->get_all($pp->schema());

        if (false || isset($parent_selectable))
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

        return $choices;
    }

    public function getParent():string
    {
        return ChoiceType::class;
    }
}