<?php declare(strict_types=1);

namespace App\Form\Post\News;

use App\Command\News\NewsCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\Type\DatepickerType;
use App\Form\Type\SummernoteType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;

class NewsDelType extends AbstractType
{
    public function __construct(
        protected AccessFieldSubscriber $access_field_subscriber
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('subject', TextType::class, [
                'disabled'  => true,
            ])
            ->add('location', TextType::class, [
                'disabled'  => true,
            ])
            ->add('event_at', DatepickerType::class, [
                'disabled'  => true,
            ])
            ->add('content', SummernoteType::class, [
                'disabled'  => true,
            ])
            ->add('submit', SubmitType::class);

            $this->access_field_subscriber->add('access',
                ['admin', 'user', 'guest'], ['disabled' => true]);
            $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => NewsCommand::class,
        ]);
    }
}