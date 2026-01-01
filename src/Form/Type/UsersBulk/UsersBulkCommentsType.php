<?php declare(strict_types=1);

namespace App\Form\Type\UsersBulk;

use App\Command\UsersBulk\UsersBulkCommentsCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class UsersBulkCommentsType extends AbstractType
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
      ->add('comments', TextType::class)
      ->add('verify', CheckboxType::class)
      ->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefaults([
      'data_class'  => UsersBulkCommentsCommand::class,
    ]);
  }
}