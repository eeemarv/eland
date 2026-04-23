<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersActiveCommand;
use App\Form\Type\Field\BtnChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersActiveType extends AbstractType
{
  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $builder->add('is_active', BtnChoiceType::class, [
      'choices' => [
        'active'  => true,
        'inactive'  => false,
      ],
    ]);
    $builder->add('send_email', CheckboxType::class);
    $builder->add('send_email_cc', CheckboxType::class);
    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', UsersActiveCommand::class);
  }
}