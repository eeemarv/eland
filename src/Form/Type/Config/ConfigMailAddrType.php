<?php declare(strict_types=1);

namespace App\Form\Type\Config;

use App\Command\Config\ConfigMailAddrCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigMailAddrType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder->add('admin', CollectionType::class, [
            'entry_type'        => EmailType::class,
            'allow_add'         => true,
            'allow_delete'      => true,
            'delete_empty'      => true,
            'prototype'         => true,
        ])
        ->add('support', CollectionType::class, [
            'entry_type'        => EmailType::class,
            'allow_add'         => true,
            'allow_delete'      => true,
            'delete_empty'      => true,
            'prototype'         => true,
        ])
        ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', ConfigMailAddrCommand::class);
    }
}