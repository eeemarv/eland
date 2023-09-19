<?php declare(strict_types=1);

namespace App\Form\Type\Tags;

use App\Command\Tags\TagsUsersCommand;
use App\Form\Type\Field\TagsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TagsUsersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('tags', TagsType::class, [
            'tag_type'  => 'users',
        ]);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', TagsUsersCommand::class);
    }
}