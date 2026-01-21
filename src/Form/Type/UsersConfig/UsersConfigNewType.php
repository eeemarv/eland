<?php declare(strict_types=1);

namespace App\Form\Type\UsersConfig;

use App\Command\UsersConfig\UsersConfigNewCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersConfigNewType extends AbstractType
{
  public function __construct(
    private readonly AccessFieldSubscriber $access_field_subscriber
  )
  {
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $builder->add('days', IntegerType::class);

    $this->access_field_subscriber->add(name: 'access');
    $this->access_field_subscriber->add(name: 'access_list');
    $this->access_field_subscriber->add(name: 'access_pane');

    $builder->addEventSubscriber($this->access_field_subscriber);
    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', UsersConfigNewCommand::class);
  }
}