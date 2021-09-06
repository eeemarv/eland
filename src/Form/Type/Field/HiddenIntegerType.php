<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use App\Form\DataTransformer\IntegerTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

class HiddenIntegerType extends AbstractType
{
    public function __construct(
        protected IntegerTransformer $integer_transformer
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        parent::buildForm($builder, $options);
        $builder->addModelTransformer($this->integer_transformer);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('invalid_message', 'type.not_an_integer');
    }

    public function getParent():string
    {
        return HiddenType::class;
    }
}