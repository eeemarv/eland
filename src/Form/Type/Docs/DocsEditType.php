<?php declare(strict_types=1);

namespace App\Form\Type\Docs;

use App\Command\Docs\DocsCommand;
use App\Controller\Autocomplete\AutocompleteAccountsController;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\Type\Field\AutocompleteType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocsEditType extends AbstractType
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

    $builder->add('file_location', TextType::class, [
      'disabled'  => true,
    ]);

    $builder->add('original_filename', TextType::class, [
      'disabled'  => true,
    ]);

    $builder->add('name', TextType::class);
    $builder->add('map_name', AutocompleteType::class, [
      'route' => 'autocomplete_doc_map_names',
    ]);
    $builder->add('submit', SubmitType::class);

    $this->access_field_subscriber->add();
    $builder->addEventSubscriber($this->access_field_subscriber);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', DocsCommand::class);
  }
}