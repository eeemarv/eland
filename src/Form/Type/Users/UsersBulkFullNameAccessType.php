<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersBulkFullNameAccessCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersBulkFullNameAccessType extends AbstractType
{
  public function __construct(
    private readonly AccessFieldSubscriber $access_field_subscriber
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
      ->add('verify', CheckboxType::class)
      ->add('submit', SubmitType::class);

    $this->access_field_subscriber->add();
    $builder->addEventSubscriber($this->access_field_subscriber);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefaults([
      'data_class'  => UsersBulkFullNameAccessCommand::class,
    ]);
  }
}