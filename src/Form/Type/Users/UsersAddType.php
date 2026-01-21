<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersAddCommand;
use App\Form\Type\Field\TypeaheadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersAddType extends AbstractType
{
  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $builder->add('name', TypeaheadType::class, [
      'add'           => 'usernames',
      'render_omit'   => $options['render_omit'],
    ]);

    $builder->add('email', EmailType::class);

    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', UsersAddCommand::class);
    $resolver->setDefault('render_omit', '');
    $resolver->setAllowedTypes('render_omit', 'string');
  }
}