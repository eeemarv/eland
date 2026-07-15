<?php declare(strict_types=1);

namespace App\Form\Type\Tags;

use App\Command\Tags\TagsDefCommand;
use App\Enum\TagTypeEnum;
use App\Form\DataTransformer\ColorTransformer;
use App\Form\Type\Field\UniqueCheckType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TagsDefType extends AbstractType
{
  public function __construct(
    private readonly ColorTransformer $color_transformer
  )
  {
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $opt_ary = [];

    if ($options['del'] === true)
    {
      $opt_ary = ['disabled' => true];
    }

    $tag_type = $options['tag_type'];

    $builder->add('txt', UniqueCheckType::class, [
      'route' => 'unique_check_tags_txt',
      'route_params' => [
        'tag_type'  => $tag_type,
      ],
      ...$opt_ary,
    ]);

    $builder->add('bg_color', ColorType::class, $opt_ary);
    $builder->add('txt_color', ColorType::class, $opt_ary);
    $builder->add('description', TextType::class, $opt_ary);
    $builder->add('submit', SubmitType::class);

    $builder
      ->get('bg_color')
      ->addModelTransformer($this->color_transformer);
    $builder
      ->get('txt_color')
      ->addModelTransformer($this->color_transformer);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('del', false);
    $resolver->setAllowedTypes('del', 'bool');
    $resolver->setDefault('txt_omit', '');
    $resolver->setAllowedTypes('txt_omit', 'string');
    $resolver->setDefault('tag_type', null);
    $resolver->setRequired('tag_type');
    $resolver->setAllowedTypes('tag_type', 'string');
    $resolver->setAllowedValues('tag_type', TagTypeEnum::values());
    $resolver->setDefault('data_class', TagsDefCommand::class);
  }
}