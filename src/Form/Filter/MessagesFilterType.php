<?php declare(strict_types=1);

namespace App\Form\Filter;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Form\Input\TextAddonType;
use App\Form\Input\Typeahead\TypeaheadActiveUserType;

class MessagesFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setMethod('GET');
		$builder->add('q', TextAddonType::class, [
            'required' => false,
        ]);
        $builder->add('uid', TypeaheadActiveUserType::class, [
            'required'  => false,
        ]);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection'       => false,
            'form_token_enabled'    => false,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'f';
    }
}