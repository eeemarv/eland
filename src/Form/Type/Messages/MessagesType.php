<?php declare(strict_types=1);

namespace App\Form\Type\Messages;

use App\Command\Messages\MessagesCommand;
use App\Form\DataTransformer\ValidityDaysTransformer;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\EventSubscriber\CategoryFieldSubscriber;
use App\Form\Input\LblChoiceType;
use App\Form\Type\Field\SummernoteType;
use App\Form\Type\Field\TypeaheadType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class MessagesType extends AbstractType
{
    public function __construct(
        protected AccessFieldSubscriber $access_field_subscriber,
        protected CategoryFieldSubscriber $category_field_subscriber,
        protected ValidityDaysTransformer $validity_days_transformer,
        protected ConfigService $config_service,
        protected PageParamsService $pp
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $expires_at_required = $this->config_service->get_bool('messages.fields.expires_at.required', $this->pp->schema());
        $expires_at_days_default = $this->config_service->get_int('messages.fields.expires_at.days_default', $this->pp->schema());
        $service_stuff_enabled = $this->config_service->get_bool('messages.fields.service_stuff.enabled', $this->pp->schema());
        $category_enabled = $this->config_service->get_bool('messages.fields.category.enabled', $this->pp->schema());
        $expires_at_enabled = $this->config_service->get_bool('messages.fields.expires_at.enabled', $this->pp->schema());
        $expires_at_switch_enabled = $this->config_service->get_bool('messages.fields.expires_at.switch_enabled', $this->pp->schema());
        $units_enabled = $this->config_service->get_bool('messages.fields.units.enabled', $this->pp->schema());

        if ($this->pp->is_admin())
        {
            $typeahead_add = [];
            $typeahead_add[] = ['accounts', ['status' => 'active']];

            $builder->add('user_id', TypeaheadType::class, [
                'add'       => $typeahead_add,
                'filter'    => 'accounts',
                'required'  => false,
            ]);
        }

        $builder->add('offer_want', LblChoiceType::class, [
            'choices' => [
                'offer'     => 'offer',
                'want'      => 'want',
            ],
        ]);

        if ($service_stuff_enabled)
        {
            $builder->add('service_stuff', LblChoiceType::class, [
                'choices' => [
                    'service'   => 'service',
                    'stuff'     => 'stuff',
                ],
            ]);
        }

        $builder->add('subject', TextType::class);

        $builder->add('content', SummernoteType::class);

        if ($expires_at_enabled)
        {
            $builder->add('expires_at_switch', LblChoiceType::class, [
                'choices'   => [
                    'temporal'      => 'temporal',
                    'permanent'     => 'permanent',
                ],
            ]);
        }

        if ($expires_at_enabled)
        {
            $builder->add('expires_at', IntegerType::class);
            $builder->get('expires_at')
                ->addModelTransformer($this->validity_days_transformer);
        }

        if ($units_enabled)
        {
            $builder->add('amount', IntegerType::class);
            $builder->add('units', TextType::class);
        }

        $builder->add('image_files', HiddenType::class);
        $builder->add('submit', SubmitType::class);

        $this->access_field_subscriber->add('access', ['user', 'guest']);
        $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('user_id_field_enabled', false);
        $resolver->setDefault('mode', 'edit');
        $resolver->setDefault('data_class', MessagesCommand::class);

        $resolver->setAllowedTypes('user_id_field_enabled', 'bool');
        $resolver->setAllowedTypes('mode', 'string');
        $resolver->setAllowedValues('mode', ['add', 'edit', 'del']);
    }
}