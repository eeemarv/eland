<?php declare(strict_types=1);

namespace App\Form\Type\Forum;

use App\Command\Forum\ForumTopicCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\Type\Field\SummernoteType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;

class ForumTopicDelType extends AbstractType
{
    public function __construct(
        protected AccessFieldSubscriber $access_field_subscriber
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('subject', TextType::class, [
            'disabled'  => true,
        ]);
        $builder->add('content', SummernoteType::class, [
            'disabled'  => true,
        ]);
        $builder->add('submit', SubmitType::class);

        $this->access_field_subscriber->add('access',
            ['admin', 'user', 'guest'], ['disabled' => true]);
        $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', ForumTopicCommand::class);
    }
}