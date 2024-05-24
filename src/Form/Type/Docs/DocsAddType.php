<?php declare(strict_types=1);

namespace App\Form\Type\Docs;

use App\Command\Docs\DocsCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocsAddType extends AbstractType
{
    public function __construct(
        protected AccessFieldSubscriber $access_field_subscriber,
        protected TypeaheadService $typeahead_service,
        protected PageParamsService $pp
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $data_typeahead = $this->typeahead_service->ini($this->pp)
            ->add('doc_map_names', [])
            ->str_raw();

        $builder
            ->add('file', FileType::class)
            ->add('name', TextType::class)
            ->add('map_name', TextType::class, [
                'attr'  => [
                    'data-typeahead'    => $data_typeahead,
                ],
            ])
            ->add('submit', SubmitType::class);

        $this->access_field_subscriber->add('access', ['admin', 'user', 'guest']);
        $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefaults([
            'data_class'    => DocsCommand::class,
        ]);
    }
}