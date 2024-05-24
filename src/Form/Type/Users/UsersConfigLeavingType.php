<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersConfigLeavingCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersConfigLeavingType extends AbstractType
{
    public function __construct(
        protected AccessFieldSubscriber $access_field_subscriber,
        protected PageParamsService $pp,
        protected ConfigService $config_service
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $transactions_enabled = $this->config_service->get_bool('transactions.enabled', $this->pp->schema());

        if ($transactions_enabled)
        {
            $builder->add('equilibrium', IntegerType::class);
            $builder->add('auto_deactivate', CheckboxType::class);
        }

        $this->access_field_subscriber->add('access', ['admin', 'user', 'guest']);
        $this->access_field_subscriber->add('access_list', ['admin', 'user', 'guest']);
        $this->access_field_subscriber->add('access_pane', ['admin', 'user', 'guest']);
        $builder->addEventSubscriber($this->access_field_subscriber);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefaults([
            'data_class'    => UsersConfigLeavingCommand::class,
        ]);
    }
}