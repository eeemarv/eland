<?php declare(strict_types=1);

namespace App\Form\Type\Categories;

use App\Command\Categories\CategoriesNameCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoriesNameType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $opt_ary = [];

        if ($options['del'] === true)
        {
            $opt_ary = ['disabled' => true];
        }

        $builder->add('name', TextType::class, $opt_ary);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', CategoriesNameCommand::class);
        $resolver->setDefault('del', false);
        $resolver->setAllowedTypes('del', 'bool');
    }
}