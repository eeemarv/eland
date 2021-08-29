<?php declare(strict_types=1);

namespace App\Form\Type\Messages;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Form\Input\TextAddonType;
use App\Form\Input\Typeahead\TypeaheadActiveUserType;
use App\Form\Type\FilterType;

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

    public function getBlockPrefix():string
    {
        return 'f';
    }

    public function getParent():string
    {
        return FilterType::class;
    }
}