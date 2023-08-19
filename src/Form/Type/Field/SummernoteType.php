<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SummernoteType extends AbstractType
{
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
            // handled in controller for now
            // 'sanitize_html' => true,
        ]);
    }

    public function getParent(): ?string
    {
        return TextareaType::class;
    }
}