<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use App\Enum\TagTypeEnum;
use App\Form\DataTransformer\TagsTransformer;
use App\Repository\TagRepository;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class TagifyType extends AbstractType
{
  public function __construct(
    private readonly TagRepository $tag_repository,
    private readonly PageParamsService $pp,
    private readonly TagsTransformer $tags_transformer,
  )
  {
  }

  public function buildForm(FormBuilderInterface $builder, array $options):void
  {
    parent::buildForm($builder, $options);
    $builder->addModelTransformer($this->tags_transformer);
  }

  public function buildView(FormView $view, FormInterface $form, array $options):void
  {
    parent::buildView($view, $form, $options);

    $tag_ary = $this->tag_repository->get_all(
      tag_type: $options['tag_type'],
      schema: $this->pp->schema_o(),
      active_only: true,
    );

    foreach ($tag_ary as &$tag)
    {
      $tag['value'] = $tag['id'];
    }

    $view->vars['attr']['data-tagify-whitelist-value'] = json_encode($tag_ary);

    if ($options['max_tags'] !== null) {
        $view->vars['attr']['data-tagify-max-tags-value'] = $options['max_tags'];
    }
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('tag_type', null);
    $resolver->setRequired('tag_type');
    $resolver->setDefault('max_tags', null);
    $resolver->setAllowedTypes('max_tags', ['int', 'null']);
    $resolver->setAllowedTypes('tag_type', 'string');
    $resolver->setAllowedValues('tag_type', TagTypeEnum::values());
    $resolver->setDefault('attr', [
      'data-controller' => 'tagify',
    ]);
  }

  public function getParent():string
  {
    return TextType::class;
  }

  public function getBlockPrefix():string
  {
    return 'tags';
  }
}