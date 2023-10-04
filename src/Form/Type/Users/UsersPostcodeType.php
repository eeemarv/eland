<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersPostcodeCommand;
use App\Form\Type\Field\TypeaheadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersPostcodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('postcode', TypeaheadType::class, [
            'add'   => 'postcodes',
        ]);
		$builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', UsersPostcodeCommand::class);
    }
}