<?php declare(strict_types=1);

namespace App\Form\Type\Messages;

use App\Cache\ConfigCache;
use App\Command\Messages\MessagesAccessCommand;
use App\Form\DataTransformer\ValidityDaysTransformer;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\EventSubscriber\CategoryFieldSubscriber;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MessagesAccessType extends AbstractType
{
    public function __construct(
        protected AccessFieldSubscriber $access_field_subscriber,
        protected CategoryFieldSubscriber $category_field_subscriber,
        protected ValidityDaysTransformer $validity_days_transformer,
        protected ConfigCache $config_cache,
        protected PageParamsService $pp
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        /*
        $builder->add('access', BtnChoiceType::class, [
            'user'  => 'user',
            'guest' => 'guest',
        ]);
        */
        $builder->add('submit', SubmitType::class);

        $this->access_field_subscriber->add('access', ['user', 'guest']);
        $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', MessagesAccessCommand::class);
    }
}