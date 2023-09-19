<?php declare(strict_types=1);

namespace App\Form\Type\Transactions;

use App\Command\Transactions\TransactionsAddCommand;
use App\Form\Type\Field\BtnChoiceType;
use App\Form\Type\Field\TypeaheadType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;

class TransactionsAddType extends AbstractType
{
    public function __construct(
        protected ConfigService $config_service,
        protected PageParamsService $pp
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $service_stuff_enabled = $this->config_service->get_bool('transactions.fields.service_stuff.enabled', $this->pp->schema());

        $from_remote_account_options = [];
        $to_remote_account_options = [];

        $typeahead_add = [];
        $typeahead_add[] = ['accounts', ['status' => 'active-user']];
        $typeahead_add[] = ['accounts', ['status' => 'intersystem']];

        if ($this->pp->is_admin())
        {
            $typeahead_add[] = ['accounts', ['status' => 'pre-active']];
            $typeahead_add[] = ['accounts', ['status' => 'post-active']];
        }

        $local_typeahead_options = [
            'filter'    => 'accounts',
            'add'       => $typeahead_add,
        ];

        $from_id_options = $local_typeahead_options;
        $to_id_options = $local_typeahead_options;
        $to_remote_id_options = [
            'filter'    => 'accounts',
            'add'       => [],
        ];

        $remote_amount_options = [];

        $builder->add('from_id', TypeaheadType::class, $from_id_options);
        $builder->add('from_remote_account', TextType::class, $from_remote_account_options);
        $builder->add('to_id', TypeaheadType::class, $to_id_options);
        $builder->add('to_remote_id', TypeaheadType::class, $to_remote_id_options);
        $builder->add('to_remote_account', TextType::class, $to_remote_account_options);
        $builder->add('amount', IntegerType::class);
        $builder->add('remote_amount', IntegerType::class);
        $builder->add('description', TextType::class);
        $builder->add('service_stuff', BtnChoiceType::class, [
                'choices'       => [
                    'service'               => 'service',
                    'stuff'                 => 'stuff',
                ],
            ]);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', TransactionsAddCommand::class);
    }
}