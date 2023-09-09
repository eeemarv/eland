<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use App\Enum\TagTypeEnum;
use App\Form\DataTransformer\TagsTransformer;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class TagsType extends AbstractType
{
    public function __construct(
        protected TypeaheadService $typeahead_service,
        protected PageParamsService $pp,
        protected TagsTransformer $tags_transformer,
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
        $this->typeahead_service->ini($this->pp);
        $this->typeahead_service->add('tags', [
            'tag_type'  => $options['tag_type'],
        ]);

        $data_typeahead = $this->typeahead_service->str_raw();

        parent::buildView($view, $form, $options);

        $view->vars['attr'] = [
            ...$options['attr'],
            'data-tags-typeahead'   => $data_typeahead,
        ];

        if (isset($process_ary['render']))
        {
            $view->vars['render_omit'] = true;
        }
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('tag_type', null);
        $resolver->setRequired('tag_type');
        $resolver->setAllowedTypes('tag_type', 'string');
        $resolver->setAllowedValues('tag_type', TagTypeEnum::values());
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