<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersAccountCodeCommand;
use App\Form\Type\Field\UniqueCheckType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersAccountCodeType extends AbstractType
{
  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $builder->add('code', UniqueCheckType::class, [
      'route' => 'unique_check_account_codes',
    ]);

    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', UsersAccountCodeCommand::class);
  }
}