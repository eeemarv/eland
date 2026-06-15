<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersTagsCommand;
use App\Form\Type\Field\TagifyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersTagsType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options):void
  {
    $builder->add('tags', TagifyType::class, [
      'tag_type'  => 'users',
    ]);

    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', UsersTagsCommand::class);
  }
}