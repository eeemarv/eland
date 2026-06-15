<?php declare(strict_types=1);

namespace App\Form\Type\UsersBulk;

use App\Command\UsersBulk\UsersBulkTagsDelCommand;
use App\Form\Type\Field\TagifyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersBulkTagsDelType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options):void
  {
    $builder->add('selected', HiddenType::class);

    $builder->add('tags', TagifyType::class, [
      'tag_type'  => 'users',
      'max_tags'  => 1,
    ]);

    $builder->add('verify', CheckboxType::class);

    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', UsersBulkTagsDelCommand::class);
  }
}