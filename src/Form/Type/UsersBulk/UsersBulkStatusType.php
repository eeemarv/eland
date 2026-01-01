<?php declare(strict_types=1);

namespace App\Form\Type\UsersBulk;

use App\Command\UsersBulk\UsersBulkStatusCommand;
use App\Form\Type\Field\StatusSelectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersBulkStatusType extends AbstractType
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
      ->add('status', StatusSelectType::class)
      ->add('verify', CheckboxType::class)
      ->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefaults([
      'data_class'  => UsersBulkStatusCommand::class,
    ]);
  }
}