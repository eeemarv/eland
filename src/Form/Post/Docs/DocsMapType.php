<?php declare(strict_types=1);

namespace App\Form\Post\Docs;

use App\Command\Docs\DocsMapCommand;
use App\Form\Input\UniqueTextAddonType;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocsMapType extends AbstractType
{
    public function __construct(
        protected TypeaheadService $typeahead_service,
        protected PageParamsService $pp
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $data_typeahead = $this->typeahead_service->ini($this->pp)
            ->add('doc_map_names', [])
            ->str_raw([
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
            'data_class'    => DocsMapCommand::class,
        ]);
    }
}