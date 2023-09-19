<?php declare(strict_types=1);

namespace App\Form\Type\Tags;

use App\Command\Contacts\ContactsCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TagsFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('contact_type_id', TextType::class, [
            'disabled'      => true,
        ]);

        $builder->add('value', TextType::class, [
            'disabled'      => true,
        ]);

        $builder->add('comments', TextType::class, [
            'disabled'      => true,
        ]);

        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        // $resolver->setDefaults('data_class', ContactsCommand::class);
    }
}
