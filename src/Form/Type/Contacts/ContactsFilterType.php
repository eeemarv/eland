<?php declare(strict_types=1);

namespace App\Form\Type\Contacts;

use App\Command\Contacts\ContactsFilterCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\Type\FilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Form\Type\TypeaheadType;
use App\Repository\ContactRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactsFilterType extends AbstractType
{
    const USTATUS = ['active', 'new', 'leaving', 'inactive', 'ip', 'im', 'extern'];

    public function __construct(
        protected ConfigService $config_service,
        protected ContactRepository $contact_repository,
        protected AccessFieldSubscriber $access_field_subscriber,
        protected PageParamsService $pp
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $ustatus_choices = [];

        foreach (self::USTATUS as $us)
        {
            $label = 'status.' . $us;

            if ($us === 'new')
            {
                if (!$this->config_service->get_bool('users.new.enabled', $this->pp->schema()))
                {
                    continue;
                }

                $label .= '_only';
            }

            if ($us === 'leaving')
            {
                if (!$this->config_service->get_bool('users.leaving.enabled', $this->pp->schema()))
                {
                    continue;
                }

                $label .= '_only';
            }

            $ustatus_choices[$label] = $us;
        }

        $contact_types = $this->contact_repository->get_all_contact_types($this->pp->schema());

        $type_choices = [];
        $type_choice_attr = [];

        foreach ($contact_types as $row)
        {
            $type_choices[$row['name']] = $row['id'];
            $type_choice_attr[$row['name']] = ['data-abbrev' => $row['abbrev']];
        }

        $typeahead_accounts_add = [];
        $typeahead_accounts_add[] = ['accounts', ['status' => 'active']];
        $typeahead_accounts_add[] = ['accounts', ['status' => 'extern']];
        $typeahead_accounts_add[] = ['accounts', ['status' => 'inactive']];
        $typeahead_accounts_add[] = ['accounts', ['status' => 'im']];
        $typeahead_accounts_add[] = ['accounts', ['status' => 'ip']];

        $builder->add('q', TextType::class, [
            'required' => false,
        ]);

		$builder->add('user', TypeaheadType::class, [
            'add'           => $typeahead_accounts_add,
            'filter'        => 'accounts',
            'required' 		=> false,
        ]);

        $builder->add('type', ChoiceType::class, [
            'empty_data'    => null,
            'choices'       => $type_choices,
            'choice_attr'   => $type_choice_attr,
            'choice_translation_domain' => false,
            'required'      => false,
        ]);

		$builder->add('ustatus', ChoiceType::class, [
            'empty_data'    => null,
            'choices'       => $ustatus_choices,
            'required'      => false,
        ]);

        $this->access_field_subscriber->add('access', ['admin', 'user', 'guest'], [
            'multiple' => true,
            'required' => false,
        ]);

        $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function getParent():string
    {
        return FilterType::class;
    }

    public function getBlockPrefix():string
    {
        return 'f';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => ContactsFilterCommand::class,
        ]);
    }
}