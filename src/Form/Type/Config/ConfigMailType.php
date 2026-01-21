<?php declare(strict_types=1);

namespace App\Form\Type\Config;

use App\Command\Config\ConfigMailCommand;
use App\Form\Type\Field\ColorChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigMailType extends AbstractType
{
  public function buildForm(
    FormBuilderInterface $builder,
    array $options
  ):void
  {
    $choices = [
      'gs' => 'grey',
      'rd' => 'red',
      'gn' => 'green',
      'bw' => 'blue',
      'vt' => 'violet',
      'gl' => 'yellow',
      'cn' => 'cyan',
    ];

    $color_ary = [
      'gs'  => '#afafaf',
      'rd'  => '#cf958f',
      'gn'  => '#a8cf8f',
      'bw'  => '#8fb3cf',
      'vt'  => '#a08fcf',
      'gl'  => '#cfc08f',
      'cn'  => '#8fcfb5',
    ];

    $builder->add('enabled', CheckboxType::class);
    $builder->add('tag', TextType::class);
    $builder->add('background', ColorChoiceType::class, [
      'choices'   => $choices,
      'color_ary' => $color_ary,
      'multiple'  => false,
      'required'  => true
    ]);
    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', ConfigMailCommand::class);
  }
}