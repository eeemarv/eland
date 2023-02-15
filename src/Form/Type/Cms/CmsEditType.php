<?php declare(strict_types=1);

namespace App\Form\Type\Cms;

use App\Command\Cms\CmsEditCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CmsEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('route', HiddenType::class)
            ->add('route_en', HiddenType::class)
            ->add('role', HiddenType::class)
            ->add('role_en', HiddenType::class)
            ->add('all_params', HiddenType::class)
            ->add('content', HiddenType::class)
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => CmsEditCommand::class,
        ]);
    }
}