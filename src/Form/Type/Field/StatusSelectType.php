<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StatusSelectType extends AbstractType
{
  public function __construct(
  )
  {
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('choices', [
      'status.inactive'   => 0,
      'status.active'     => 1,
      'status.leaving'    => 2,
      'status.ip'         => 5,
      'status.im'         => 6,
      'status.extern'     => 7,
    ]);
  }

  public function getParent():string
  {
    return ChoiceType::class;
  }
}