<?php declare(strict_types=1);

namespace App\Form\Type\Messages;

use App\Command\Messages\MessagesCategoryCommand;
use App\Form\Type\Field\CategorySelectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MessagesCategoryEditType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder->add('category_id', CategorySelectType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', MessagesCategoryCommand::class);
    }
}