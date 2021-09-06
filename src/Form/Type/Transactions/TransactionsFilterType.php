<?php declare(strict_types=1);

namespace App\Form\Type\Transactions;

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

class TransactionsFilterType extends AbstractType
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
        $service_stuff_enabled = $this->config_service->get_bool('transactions.fields.service_stuff.enabled', $this->pp->schema());

        $typeahead_add = [];

        $typeahead_add[] = ['accounts', ['status' => 'active']];

        if (!$this->pp->is_guest())
        {
            $typeahead_add[] = ['accounts', ['status' => 'extern']];
        }

        if ($this->pp->is_admin())
        {
            $typeahead_add[] = ['accounts', ['status' => 'inactive']];
            $typeahead_add[] = ['accounts', ['status' => 'im']];
            $typeahead_add[] = ['accounts', ['status' => 'ip']];
        }

        $builder->add('q', TextType::class, [
            'required' => false,
        ]);

		$builder->add('from_account', TypeaheadType::class, [
            'add'           => $typeahead_add,
            'filter'		=> 'accounts',
            'required'      => false,
        ]);

		$builder->add('to_account', TypeaheadType::class, [
            'add'           => $typeahead_add,
            'filter'        => 'accounts',
            'required' 		=> false,
        ]);

		$builder->add('account_logic', ChoiceType::class, [
            'choices'	=> [
                'logic.and'	=> 'and',
                'logic.or'	=> 'or',
                'logic.nor'	=> 'nor',
            ],
            'required'  => true,
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

        if ($service_stuff_enabled)
        {
            $choices = [
                'service'               => 'srvc',
                'stuff'                 => 'stff',
                'null_service_stuff'    => 'null',
            ];

            $builder->add('srvc', BtnChoiceType::class, [
                'choices'       => $choices,
                'multiple'      => true,
                'required'      => false,
            ]);
        }

        $action = $this->url_generator->generate('transactions', $this->pp->ary(), UrlGeneratorInterface::ABSOLUTE_PATH);

        $builder->setAction($action);
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
            'data_class'                => TransactionsFilterCommand::class,
        ]);
    }
}