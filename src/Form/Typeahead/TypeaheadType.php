<?php declare(strict_types=1);

namespace App\Form\Typeahead;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

use App\Form\Input\TextAddonType;
use App\Form\Typeahead\TypeaheadTypeAttr;

class TypeaheadType extends AbstractType
{
    private $typeahead_type_attr;

    public function __construct(TypeaheadTypeAttr $typeaheadTypeAttr)
    {
        $this->typeaheadTypeAttr = $typeaheadTypeAttr;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['typeahead_attr'] = $this->typeaheadTypeAttr->get($options);
    }    

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'source_id'         => null,
            'source_route'      => null,     
            'source_params'     => null,
            'source'            => null, 
            'process'           => null,
        ]);
    }

    public function getParent()
    {
        return TextAddonType::class;
    }

    public function getBlockPrefix()
    {
        return 'typeahead';
    }
}