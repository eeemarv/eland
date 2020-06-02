<?php declare(strict_types=1);

namespace App\Form\Input;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

class UniqueTextAddonType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (isset($options['item_not_unique_trans_key']))
        {
            $view->vars['item_not_unique_trans_key'] = $options['item_not_unique_trans_key'];
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'item_not_unique_trans_key' => null,
        ]);
    }

    public function getParent()
    {
        return TextAddonType::class;
    }

    public function getBlockPrefix()
    {
        return 'unique_addon';
    }
}