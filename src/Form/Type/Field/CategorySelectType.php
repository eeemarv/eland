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
use Symfony\Contracts\Translation\TranslatorInterface;

class CategorySelectType extends AbstractType
{
    public function __construct(
        protected TranslatorInterface $translator,
        protected CategoryRepository $category_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('parent_selectable', false);
        $resolver->setAllowedTypes('parent_selectable', 'bool');
        $resolver->setDefault('null_selectable', false);
        $resolver->setAllowedTypes('null_selectable', 'bool');
        $resolver->setDefault('all_choice', false);
        $resolver->setAllowedTypes('all_choice', 'bool');

        $resolver->setDefault('choice_loader', function (Options $options){
            return ChoiceList::loader($this,
                new CategoriesChoiceLoader(
                    $options['parent_selectable'],
                    $options['null_selectable'],
                    $options['all_choice'],
                    $this->category_repository,
                    $this->pp,
                    $this->translator
            ));
        });
    }

    public function getParent():string
    {
        return ChoiceType::class;
    }
}