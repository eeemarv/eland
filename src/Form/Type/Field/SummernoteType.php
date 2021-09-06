<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use App\Form\DataTransformer\HtmlPurifyTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SummernoteType extends AbstractType
{
    public function __construct(
        protected HtmlPurifyTransformer $html_purify_transformer
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    )
    {
        $builder->addModelTransformer($this->html_purify_transformer);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'attr'                  => [
                'rows'              => 5,
                'minlength'         => 10,
                'maxlength'         => 100000,
                'data-summernote'   => '',
                'class'             => 'summernote',
            ],
        ]);
    }

    public function getParent()
    {
        return TextareaType::class;
    }
}