<?php declare(strict_types=1);

namespace App\Form\Type\Logs;

use App\Command\Logs\LogsFilterCommand;
use App\Form\Type\Field\AutocompleteAccountType;
use App\Form\Type\Field\AutocompleteType;
use App\Form\Type\Filter\FilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LogsFilterType extends AbstractType
{
  public function __construct()
  {
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options
  ):void
  {
    $builder->add('q', TextType::class, [
      'required' => false,
    ]);

    $builder->add('user', AutocompleteAccountType::class, [
      'account_group' => 'all',
      'required'      => false,
    ]);

    $builder->add('type', AutocompleteType::class, [
      'route'     => 'autocomplete_log_types',
      'required'  => false,
    ]);
  }

  public function getParent():string
  {
    return FilterType::class;
  }

  public function getBlockPrefix():string
  {
    return 'f';
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', LogsFilterCommand::class);
  }
}