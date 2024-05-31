<?php declare(strict_types=1);

namespace App\Form\Type\Config;

use App\Command\Config\ConfigModulesCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigModulesType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder->add('messages_enabled', CheckboxType::class);
        $builder->add('transactions_enabled', CheckboxType::class);
        $builder->add('news_enabled', CheckboxType::class);
        $builder->add('docs_enabled', CheckboxType::class);
        $builder->add('forum_enabled', CheckboxType::class);
        $builder->add('support_form_enabled', CheckboxType::class);
        $builder->add('home_menu_enabled', CheckboxType::class);
        $builder->add('contact_form_enabled', CheckboxType::class);
        $builder->add('register_form_enabled', CheckboxType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', ConfigModulesCommand::class);
    }
}