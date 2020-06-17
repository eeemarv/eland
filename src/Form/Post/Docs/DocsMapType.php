<?php declare(strict_types=1);

namespace App\Form\Post\Docs;

use App\Form\Input\UniqueTextAddonType;
use App\Service\TypeaheadService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocsMapType extends AbstractType
{
    protected TypeaheadService $typeahead_service;

    public function __construct(
        TypeaheadService $typeahead_service
    )
    {
        $this->typeahead_service = $typeahead_service;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $data_typeahead = $this->typeahead_service->ini()
            ->add('doc_map_names', [])
            ->str([
                'check_uniqueness'  => true,
                'initial_value'     => $options['initial_value'],
            ]);

        $builder
            ->add('name', UniqueTextAddonType::class, [
                'attr'  => [
                    'data-typeahead' => $data_typeahead,
                ]
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'initial_value' => '',
        ]);
    }
}