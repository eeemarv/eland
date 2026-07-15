<?php declare(strict_types=1);

namespace App\Form\Type\Docs;

use App\Command\Docs\DocsMapCommand;
use App\Form\Type\Field\UniqueCheckType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocsMapType extends AbstractType
{
  public function __construct(
  )
  {
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $builder->add('name', UniqueCheckType::class, [
      'route' => 'unique_check_doc_map_names',
    ]);

    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', DocsMapCommand::class);
  }
}