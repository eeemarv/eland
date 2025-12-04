<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersColsContactsType extends AbstractType
{
  public function __construct(
  )
  {
  }

  private function sanitize_field_name(string $abbrev): string
  {
    $field = trim($abbrev);
    $field = strtolower($field);
    $field = preg_replace('/[^a-z0-9_]+/', '_', $field);
    $field = ltrim($field, '_');
    if (preg_match('/^[0-9]/', $field))
    {
      $field = '_' . $field;
    }
    return $field;
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options
  ):void
  {
    $field_map = [];

    foreach ($options['contact_types'] as $t)
    {
      $field_name = $this->sanitize_field_name($t['abbrev']);
      $field_map[$field_name] = $t['abbrev'];
      $builder->add($field_name, CheckboxType::class, [
        'label'    => $t['name'],
        'translation_domain'  => false,
        'required'  => false,
      ]);
    }

    $builder->setAttribute('field_map', $field_map);
  }

  public function configureOptions(OptionsResolver $resolver): void
  {
    $resolver->setRequired('contact_types');
    $resolver->setAllowedTypes('contact_types', 'array');
    $resolver->setDefault('data_class', null);
  }
}
