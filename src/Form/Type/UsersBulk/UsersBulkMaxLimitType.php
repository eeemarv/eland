<?php declare(strict_types=1);

namespace App\Form\Type\UsersBulk;

use App\Command\UsersBulk\UsersBulkMaxLimitCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersBulkMaxLimitType extends AbstractType
{
  public function __construct(
  )
  {
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options
  ):void
  {
    $builder
      ->add('selected', HiddenType::class)
      ->add('max_limit', IntegerType::class)
      ->add('verify', CheckboxType::class)
      ->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefaults([
      'data_class'  => UsersBulkMaxLimitCommand::class,
    ]);
  }
}