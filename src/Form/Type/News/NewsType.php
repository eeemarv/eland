<?php declare(strict_types=1);

namespace App\Form\Type\News;

use App\Command\News\NewsCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\Type\DatepickerType;
use App\Form\Type\SummernoteType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;

class NewsType extends AbstractType
{
    public function __construct(
        protected AccessFieldSubscriber $access_field_subscriber
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('subject', TextType::class)
            ->add('location', TextType::class)
            ->add('event_at', DatepickerType::class)
            ->add('content', SummernoteType::class)
            ->add('submit', SubmitType::class);

        $this->access_field_subscriber->add('access', ['admin', 'user', 'guest']);
        $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => NewsCommand::class,
        ]);
    }
}