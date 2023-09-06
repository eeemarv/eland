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

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $from_remote_account_options = [];
        $to_remote_account_options = [];

        $typeahead_add = [];
        $typeahead_add[] = ['accounts', ['status' => 'active']];
        $typeahead_add[] = ['accounts', ['status' => 'extern']];

        if ($this->pp->is_admin())
        {

            $typeahead_add[] = ['accounts', ['status' => 'inactive']];
            $typeahead_add[] = ['accounts', ['status' => 'im']];
            $typeahead_add[] = ['accounts', ['status' => 'ip']];
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



        // $service_stuff_enabled = $this->config_service->get_bool('transactions.fields.service_stuff.enabled', $this->pp->schema());

        $builder
            ->add('from_id', TypeaheadType::class, $from_id_options)
            ->add('from_remote_account', TextType::class, $from_remote_account_options)
            ->add('to_id', TypeaheadType::class, $to_id_options)
            ->add('to_remote_id', TypeaheadType::class, $to_remote_id_options)
            ->add('to_remote_account', TextType::class, $to_remote_account_options)
            ->add('amount', IntegerType::class)
            ->add('remote_amount', IntegerType::class)
            ->add('description', TextType::class)
            ->add('service_stuff', BtnChoiceType::class, [
                'choices'       => [
                    'service'               => 'service',
                    'stuff'                 => 'stuff',
                ],
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'        => TransactionsAddCommand::class,
        ]);
    }
}