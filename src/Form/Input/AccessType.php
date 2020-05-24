<?php declare(strict_types=1);

namespace App\Form\Input;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

use App\Form\Input\TextAddonType;
use App\Form\Typeahead\TypeaheadTypeAttr;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class AccessType extends AbstractType
{
    protected $typeaheadTypeAttr;

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
            'expanded'          => true,
            'multiple'          => false,
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix()
    {
        return 'access';
    }
}