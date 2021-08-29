<?php declare(strict_types=1);

namespace App\Form\Type\Contacts;

use App\Command\Contacts\ContactsCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\Type\TypeaheadType;
use App\Repository\ContactRepository;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactsType extends AbstractType
{
    const FORMAT = [
        'adr'	=> [
            'fa'		=> 'map-marker',
            'lbl'		=> 'label.address',
            'help'	    => 'help.address',
        ],
        'gsm'	=> [
            'fa'		=> 'mobile',
            'lbl'		=> 'label.mobile',
        ],
        'tel'	=> [
            'fa'		=> 'phone',
            'lbl'		=> 'label.phone',
        ],
        'mail'	=> [
            'fa'		=> 'envelope-o',
            'lbl'		=> 'label.email',
            'type'		=> 'email',
        ],
        'web'	=> [
            'fa'		=> 'link',
            'lbl'		=> 'label.website',
            'type'		=> 'url',
        ],
    ];

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
        $user_id_enabled = $options['user_id_enabled'];

        $contact_types = $this->contact_repository->get_all_contact_types($this->pp->schema());

        $choices = [];
        $choice_attr = [];

        foreach ($contact_types as $row)
        {
            $choices[$row['name']] = $row['id'];
            $choice_attr[$row['name']] = ['data-abbrev' => $row['abbrev']];
        }

        $contacts_format = [];

        foreach (self::FORMAT as $abbrev => $data)
        {
            $contacts_format[$abbrev] = [
                'fa'    => $data['fa'],
                'lbl'   => $this->translator->trans($data['lbl']),
            ];

            if (isset($data['type']))
            {
                $contacts_format[$abbrev]['type'] = $data['type'];
            }

            if (isset($data['help']))
            {
                $contacts_format[$abbrev]['help'] = $this->translator->trans($data['help']);
            }
        }

        if ($user_id_enabled)
        {
            $builder->add('user_id', TypeaheadType::class, [
                'add'   => [
                    ['accounts', ['status' => 'active']],
                    ['accounts', ['status' => 'inactive']],
                    ['accounts', ['status' => 'ip']],
                    ['accounts', ['status' => 'im']],
                    ['accounts', ['status' => 'extern']],
                ],
                'filter'    => 'accounts',
            ]);
        }

        $builder->add('contact_type_id', ChoiceType::class, [
            'choices'       => $choices,
            'choice_attr'   => $choice_attr,
            'choice_translation_domain' => false,
        ]);
        $builder->add('value', TextType::class, [
            'attr' => [
                'data-contacts-format' => json_encode($contacts_format),
            ],
        ]);
        $builder->add('comments', TextType::class);
        $builder->add('submit', SubmitType::class);

        $this->access_field_subscriber->add('access', ['admin', 'user', 'guest']);
        $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'user_id_enabled'   => false,
            'data_class'        => ContactsCommand::class,
        ]);
    }
}
