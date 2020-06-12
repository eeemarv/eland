<?php declare(strict_types=1);

namespace App\Form\Post\Messages;

use App\Form\DataTransformer\ValidityDaysTransformer;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use App\Form\EventSubscriber\CategoryFieldSubscriber;
use App\Form\EventSubscriber\UserFieldSubscriber;
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
    protected UserFieldSubscriber $user_field_subscriber;
    protected ValidityDaysTransformer $validity_days_transformer;

    public function __construct(
        AccessFieldSubscriber $access_field_subscriber,
        CategoryFieldSubscriber $category_field_subscriber,
        UserFieldSubscriber $user_field_subscriber,
        ValidityDaysTransformer $validity_days_transformer
    )
    {
        $this->access_field_subscriber = $access_field_subscriber;
        $this->category_field_subscriber = $category_field_subscriber;
        $this->user_field_subscriber = $user_field_subscriber;
        $this->validity_days_transformer = $validity_days_transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('offer_want', LblChoiceType::class, [
                'choices' => [
                    'offer'     => 'offer',
                    'want'      => 'want',
                ],
            ])
            ->add('subject', TextType::class)
            ->add('content', SummernoteType::class)
            ->add('expires_at', NumberAddonType::class)
            ->add('amount', NumberAddonType::class)
            ->add('units', TextAddonType::class)
            ->add('image_files', HiddenType::class)
            ->add('submit', SubmitType::class);

        $this->access_field_subscriber->set_object_access_options(['user', 'guest']);
        $builder->addEventSubscriber($this->access_field_subscriber);
        $builder->addEventSubscriber($this->category_field_subscriber);
        $builder->addEventSubscriber($this->user_field_subscriber);
        $builder->get('expires_at')
            ->addModelTransformer($this->validity_days_transformer);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
        ]);
    }
}