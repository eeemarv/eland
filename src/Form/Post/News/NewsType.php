<?php declare(strict_types=1);

namespace App\Form\Post\News;

use App\Command\News\NewsCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\Input\Datepicker\DatepickerType;
use App\Form\Input\Summernote\SummernoteType;
use App\Form\Input\TextAddonType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;

class NewsType extends AbstractType
{
    protected AccessFieldSubscriber $access_field_subscriber;

    public function __construct(
        AccessFieldSubscriber $access_field_subscriber
    )
    {
        $this->access_field_subscriber = $access_field_subscriber;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('subject', TextType::class)
            ->add('location', TextAddonType::class)
            ->add('event_at', DatepickerType::class)
            ->add('content', SummernoteType::class)
            ->add('submit', SubmitType::class);

        $this->access_field_subscriber->set_object_access_options(['admin', 'user', 'guest']);
        $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => NewsCommand::class,
        ]);
    }
}