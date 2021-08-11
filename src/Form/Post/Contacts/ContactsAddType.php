<?php declare(strict_types=1);

namespace App\Form\Post\Contacts;

use App\Command\Docs\DocsCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Repository\ContactRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactsAddType extends AbstractType
{
    public function __construct(
        protected AccessFieldSubscriber $access_field_subscriber,
        protected TypeaheadService $typeahead_service,
        protected ConfigService $config_service,
        protected ContactRepository $contact_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $data_typeahead = $this->typeahead_service->ini()
            ->add('doc_map_names', [])
            ->str();

        $builder
            ->add('account_code', TextType::class, [
                'attr'  => [
                    'data-typeahead' => $data_typeahead,
                ]
            ])
            ->add('contact_type_id', ChoiceType::class, [

            ])
            ->add('value', TextType::class)
            ->add('comments', TextType::class)
            ->add('submit', SubmitType::class);

        $this->access_field_subscriber->add('access', ['admin', 'user', 'guest']);
        $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => DocsCommand::class,
        ]);
    }
}