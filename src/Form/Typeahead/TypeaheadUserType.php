<?php declare(strict_types=1);

namespace App\Form\Typeahead;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

use App\Form\Typeahead\TypeaheadType;
use App\Form\DataTransformer\TypeaheadUserTransformer;

class TypeaheadUserType extends AbstractType
{
    private $transformer;
    
    public function __construct(TypeaheadUserTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer($this->transformer);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'process'           => 'user',
            'addon_fa'          => 'user',
        ]);
    }

    public function getParent()
    {
        return TypeaheadType::class;
    }

    public function getBlockPrefix()
    {
        return 'typeahead_user';
    }
}