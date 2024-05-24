<?php declare(strict_types=1);

namespace App\Form\Type\Mollie;

use App\Command\Mollie\MollieFilterCommand;
use App\Command\Transactions\TransactionsFilterCommand;
use App\Form\Type\Field\BtnChoiceType;
use App\Form\Type\Field\DatepickerType;
use App\Form\Type\Filter\FilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Form\Type\Field\TypeaheadType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MollieFilterType extends AbstractType
{
    public function __construct(
        protected ConfigService $config_service,
        protected PageParamsService $pp,
        protected UrlGeneratorInterface $url_generator
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $typeahead_add = [];
        $typeahead_add[] = ['accounts', ['status' => 'active']];
        $typeahead_add[] = ['accounts', ['status' => 'extern']];
        $typeahead_add[] = ['accounts', ['status' => 'inactive']];
        $typeahead_add[] = ['accounts', ['status' => 'im']];
        $typeahead_add[] = ['accounts', ['status' => 'ip']];

        $builder->add('q', TextType::class, [
            'required' => false,
        ]);

		$builder->add('user', TypeaheadType::class, [
            'add'           => $typeahead_add,
            'filter'        => 'accounts',
            'required' 		=> false,
        ]);

		$builder->add('from_date', DatepickerType::class, [
            'attr'  => [
                'data-date-default-view-date'   => '-1y',
                'data-date-end-date'            => '0d',
            ],
            'required'  => false,
        ]);

		$builder->add('to_date', DatepickerType::class, [
            'attr'  => [
                'data-date-end-date'            => '0d',
            ],
            'required'  => false,
        ]);


        $builder->add('status', BtnChoiceType::class, [
            'choices'       => [
                'open'      => 'open',
                'paid'      => 'paid',
                'canceled'  => 'canceled',
            ],
            'multiple'      => true,
            'required'      => false,
        ]);
    }

    public function getParent():string
    {
        return FilterType::class;
    }

    public function getBlockPrefix():string
    {
        return 'f';
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefaults([
            'data_class'                => MollieFilterCommand::class,
        ]);
    }
}