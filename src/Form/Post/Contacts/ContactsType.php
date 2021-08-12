<?php declare(strict_types=1);

namespace App\Form\Post\Contacts;

use App\Command\Contacts\ContactsCommand;
use App\Form\DataTransformer\TypeaheadUserTransformer;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Repository\ContactRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
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
        protected TypeaheadUserTransformer $typeahead_user_transformer,
        protected ItemAccessService $item_access_service,
        protected TypeaheadService $typeahead_service,
        protected ConfigService $config_service,
        protected ContactRepository $contact_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $contact_types = $this->contact_repository->get_all_contact_types($this->pp->schema());

        $choices = [];
        $choice_attr = [];

        foreach ($contact_types as $row)
        {
            $choices[$row['name']] = $row['id'];
            $choice_attr[$row['name']] = ['data-abbrev' => $row['abbrev']];
        }

        $new_users_days = $this->config_service->get_int('users.new.days', $this->pp->schema());
        $new_users_enabled = $this->config_service->get_bool('users.new.enabled', $this->pp->schema());
        $leaving_users_enabled = $this->config_service->get_bool('users.leaving.enabled', $this->pp->schema());

        $show_new_status = $new_users_enabled;

        if ($show_new_status)
        {
            $new_users_access = $this->config_service->get_str('users.new.access', $this->pp->schema());
            $show_new_status = $this->item_access_service->is_visible($new_users_access);
        }

        $show_leaving_status = $leaving_users_enabled;

        if ($show_leaving_status)
        {
            $leaving_users_access = $this->config_service->get_str('users.leaving.access', $this->pp->schema());
            $show_leaving_status = $this->item_access_service->is_visible($leaving_users_access);
        }

        $data_typeahead = $this->typeahead_service->ini($this->pp)
            ->add('accounts', ['status' => 'active'])
            ->add('accounts', ['status' => 'inactive'])
            ->add('accounts', ['status' => 'ip'])
            ->add('accounts', ['status' => 'im'])
            ->add('accounts', ['status' => 'extern'])
            ->str_raw([
                'filter'        => 'accounts',
                'new_users_days'        => $new_users_days,
                'show_new_status'       => $show_new_status,
                'show_leaving_status'   => $show_leaving_status,
            ]);

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

        $builder->add('user_id', TextType::class, [
            'attr'  => [
                'data-typeahead' => $data_typeahead,
            ],
            'invalid_message' => 'user.code_not_exists',
        ]);
        $builder->get('user_id')
            ->addModelTransformer($this->typeahead_user_transformer);
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
            'data_class'    => ContactsCommand::class,
        ]);
    }
}
