<?php declare(strict_types=1);

namespace App\Form\Post\Config;

use App\Command\Config\ConfigModulesCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigModulesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('messages_enabled', CheckboxType::class)
            ->add('transactions_enabled', CheckboxType::class)
            ->add('news_enabled', CheckboxType::class)
            ->add('docs_enabled', CheckboxType::class)
            ->add('forum_enabled', CheckboxType::class)
            ->add('support_form_enabled', CheckboxType::class)
            ->add('home_menu_enabled', CheckboxType::class)
            ->add('contact_form_enabled', CheckboxType::class)
            ->add('register_form_enabled', CheckboxType::class)
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => ConfigModulesCommand::class,
        ]);
    }
}