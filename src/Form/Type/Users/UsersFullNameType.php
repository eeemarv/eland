<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersFullNameCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersFullNameType extends AbstractType
{
    public function __construct(
        protected AccessFieldSubscriber $access_field_subscriber
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        if ($options['full_name_edit_en'])
        {
            $builder->add('full_name', TextType::class);
        }

		$builder->add('submit', SubmitType::class);

        $this->access_field_subscriber->add('full_name_access',
            ['admin', 'user', 'guest']);
        $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', UsersFullNameCommand::class);
        $resolver->setDefault('full_name_edit_en', true);
        $resolver->setAllowedTypes('full_name_edit_en', 'bool');
    }
}