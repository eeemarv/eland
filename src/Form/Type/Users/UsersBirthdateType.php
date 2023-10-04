<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersBirthdateCommand;
use App\Form\Type\Field\DatepickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersBirthdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('birthdate', DatepickerType::class, [
            'transform' => 'date',
        ]);
		$builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', UsersBirthdateCommand::class);
    }
}