<?php declare(strict_types=1);

namespace App\Form\Post\Forum;

use App\Command\Forum\ForumCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\Input\Summernote\SummernoteType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForumPostType extends AbstractType
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
            ->add('content', SummernoteType::class)
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => ForumCommand::class,
        ]);
    }
}