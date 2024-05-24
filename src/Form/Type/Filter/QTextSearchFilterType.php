<?php declare(strict_types=1);

namespace App\Form\Type\Filter;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class QTextSearchFilterType extends AbstractType
{
    public function __construct()
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder->add('q', TextType::class, [
            'required' => false,
        ]);
        $builder->remove('show');
    }

    public function getParent():string
    {
        return FilterType::class;
    }

    public function getBlockPrefix():string
    {
        return 'f';
    }
}