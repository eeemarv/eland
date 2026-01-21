<?php declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LogCommentTypeExtension extends AbstractTypeExtension
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
    if (!$options['log_comment_enabled'])
    {
      return;
    }

    $builder->add('log_comment', TextType::class, [
      'required'  => false,
      'mapped'    => false,
    ]);
  }

  public function configureOptions(
    OptionsResolver $resolver,
  ):void
  {
    $resolver->setDefault('log_comment_enabled', false);
    $resolver->setAllowedTypes('log_comment_enabled', 'bool');
  }

  public static function getExtendedTypes():iterable
  {
    yield FormType::class;
  }
}
