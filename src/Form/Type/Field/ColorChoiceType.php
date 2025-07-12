<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class ColorChoiceType extends AbstractType
{
  public function buildView(
    FormView $view,
    FormInterface $form,
    array $options
  ):void
  {
    parent::buildView($view, $form, $options);

    if (isset($options['color_ary']))
    {
      $view->vars['color_ary'] = $options['color_ary'];
    }
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setRequired('color_ary');
    $resolver->setDefault('color_ary', []);
    $resolver->setAllowedTypes('color_ary', 'array');
  }

  public function getParent():string
  {
    return BtnChoiceType::class;
  }

  public function getBlockPrefix():string
  {
    return 'color_choice';
  }
}