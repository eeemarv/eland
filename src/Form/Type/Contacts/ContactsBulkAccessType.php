<?php declare(strict_types=1);

namespace App\Form\Type\Contacts;

use App\Command\Contacts\ContactsBulkAccessCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactsBulkAccessType extends AbstractType
{
  public function __construct(
    private readonly AccessFieldSubscriber $access_field_subscriber,
  )
  {
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $builder->add('selected', HiddenType::class);
    $builder->add('verify', CheckboxType::class);
    $builder->add('submit', SubmitType::class);

    $this->access_field_subscriber->add();
    $builder->addEventSubscriber($this->access_field_subscriber);    
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', ContactsBulkAccessCommand::class);
  }
}