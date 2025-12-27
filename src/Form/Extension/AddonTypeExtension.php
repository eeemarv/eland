<?php declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddonTypeExtension extends AbstractTypeExtension
{
  public static function getExtendedTypes(): iterable
  {
    yield TextType::class;
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('addon', null);
    $resolver->setAllowedTypes('addon', ['null', 'string']);
  }

  public function buildView(
    FormView $view,
    FormInterface $form,
    array $options
  ):void
  {
    if (isset($options['addon']))
    {
      $view->vars['addon'] = $options['addon'];
    }
  }
}
