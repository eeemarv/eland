<?php declare(strict_types=1);

namespace App\Form\Filter;

use App\Command\Transactions\TransactionsFilterCommand;
use App\Form\Type\DatepickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Form\Type\TypeaheadType;
use App\Service\PageParamsService;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TransactionsFilterType extends AbstractType
{
    const ADMIN_USERS_STATUS = ['inactive', 'ip', 'im', 'extern'];

    public function __construct(
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
        $typeahead_add = [['accounts', ['status' => 'active']]];

        if ($this->pp->is_admin())
        {
            foreach (self::ADMIN_USERS_STATUS as $status)
            {
                $typeadhead_add[] = ['accounts', ['status' => $status]];
            }
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
            'data_class'    => TransactionsFilterCommand::class,
        ]);
    }
}