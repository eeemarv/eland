<?php declare(strict_types=1);

namespace App\Form\Post\Docs;

use App\Command\Docs\DocsCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\Input\TextAddonType;
use App\Service\TypeaheadService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocsEditType extends AbstractType
{
    protected AccessFieldSubscriber $access_field_subscriber;
    protected TypeaheadService $typeahead_service;

    public function __construct(
        AccessFieldSubscriber $access_field_subscriber,
        TypeaheadService $typeahead_service
    )
    {
        $this->access_field_subscriber = $access_field_subscriber;
        $this->typeahead_service = $typeahead_service;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $data_typeahead = $this->typeahead_service->ini()
            ->add('doc_map_names', [])
            ->str();

        $builder
            ->add('file_location', TextAddonType::class, [
                'disabled'  => true,
            ])
            ->add('original_filename', TextAddonType::class, [
                'disabled'  => true,
            ])
            ->add('name', TextAddonType::class)
            ->add('map_name', TextAddonType::class, [
                'attr'  => [
                    'data-typeahead'    => $data_typeahead,
                ],
            ])
            ->add('submit', SubmitType::class);

        $this->access_field_subscriber->set_object_access_options(['admin', 'user', 'guest']);
        $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => DocsCommand::class,
        ]);
    }
}