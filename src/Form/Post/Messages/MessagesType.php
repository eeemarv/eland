<?php declare(strict_types=1);

namespace App\Form\Post\Messages;

use App\Form\DataTransformer\ValidityDaysTransformer;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\EventSubscriber\ActiveUserFieldSubscriber;
use App\Form\EventSubscriber\CategoryFieldSubscriber;
use App\Form\Input\LblChoiceType;
use App\Form\Input\NumberAddonType;
use App\Form\Input\Summernote\SummernoteType;
use App\Form\Input\TextAddonType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class MessagesType extends AbstractType
{
    protected AccessFieldSubscriber $access_field_subscriber;
    protected CategoryFieldSubscriber $category_field_subscriber;
    protected ActiveUserFieldSubscriber $active_user_field_subscriber;
    protected ValidityDaysTransformer $validity_days_transformer;

    public function __construct(
        AccessFieldSubscriber $access_field_subscriber,
        CategoryFieldSubscriber $category_field_subscriber,
        ActiveUserFieldSubscriber $active_user_field_subscriber,
        ValidityDaysTransformer $validity_days_transformer
    )
    {
        $this->access_field_subscriber = $access_field_subscriber;
        $this->category_field_subscriber = $category_field_subscriber;
        $this->active_user_field_subscriber = $active_user_field_subscriber;
        $this->validity_days_transformer = $validity_days_transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        error_log(json_encode($options));

        if ($options['offer_want_switch_enabled'])
        {
            $builder->add('offer_want', LblChoiceType::class, [
                'choices' => [
                    'offer'     => 'offer',
                    'want'      => 'want',
                ],
            ]);
        }

        if ($options['service_stuff_switch_enabled'])
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

        if ($options['category_id_field_enabled'])
        {
            $builder->addEventSubscriber($this->category_field_subscriber);
        }

        if ($options['expires_at_switch_enabled'])
        {
            $builder->add('expires_at_switch', LblChoiceType::class, [
                'choices'   => [
                    'temporal'      => 'temporal',
                    'permanent'     => 'permanent',
                ],
            ]);
        }

        if ($options['expires_at_field_enabled'])
        {
            $builder->add('expires_at', NumberAddonType::class);
            $builder->get('expires_at')
                ->addModelTransformer($this->validity_days_transformer);
        }

        if ($options['units_field_enabled'])
        {
            $builder->add('amount', NumberAddonType::class);
            $builder->add('units', TextAddonType::class);
        }

        $builder->add('image_files', HiddenType::class);
        $builder->add('submit', SubmitType::class);

        $this->access_field_subscriber->set_object_access_options(['user', 'guest']);
        $builder->addEventSubscriber($this->access_field_subscriber);
        $builder->addEventSubscriber($this->active_user_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'expires_at_switch_enabled'     => false,
            'expires_at_field_enabled'      => false,
            'category_id_field_enabled'     => false,
            'units_field_enabled'           => false,
            'offer_want_switch_enabled'     => false,
            'service_stuff_switch_enabled'  => false,
        ]);

        $resolver->setAllowedTypes('expires_at_switch_enabled', 'bool');
        $resolver->setAllowedTypes('expires_at_field_enabled', 'bool');
        $resolver->setAllowedTypes('category_id_field_enabled', 'bool');
        $resolver->setAllowedTypes('units_field_enabled', 'bool');
        $resolver->setAllowedTypes('offer_want_switch_enabled', 'bool');
        $resolver->setAllowedTypes('service_stuff_switch_enabled', 'bool');
    }
}