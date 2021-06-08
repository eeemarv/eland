<?php declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface as FormFormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LabelBtnChoiceTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [
            ChoiceType::class,
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined([
            'label_btn',
        ]);
    }

    public function buildView(FormView $view, FormFormInterface $form, array $options): void
    {
        if (isset($options['label_btn']))
        {
            $view->vars['label_btn'] = $options['label_btn'];
        }
    }
}
