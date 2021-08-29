<?php declare(strict_types=1);

namespace App\Form\Type\Contacts;

use App\Command\Contacts\ContactsCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Repository\ContactRepository;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactsDelType extends AbstractType
{
    public function __construct(
        protected TranslatorInterface $translator,
        protected AccessFieldSubscriber $access_field_subscriber,
        protected ContactRepository $contact_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $fa = ContactsType::FORMAT[$options['contact_type_abbrev']]['fa'] ?? 'chevron-right';

        $builder->add('contact_type_id', TextType::class, [
            'disabled'      => true,
        ]);

        $builder->add('value', TextType::class, [
            'disabled'      => true,
            'attr'          => [
                'data-fa'   => $fa,
            ]
        ]);

        $builder->add('comments', TextType::class, [
            'disabled'      => true,
        ]);

        $builder->add('submit', SubmitType::class);

        $this->access_field_subscriber->add('access', ['admin', 'user', 'guest'], [
            'disabled'  => true,
        ]);

        $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'contact_type_abbrev'   => null,
            'data_class'            => ContactsCommand::class,
        ]);
    }
}
