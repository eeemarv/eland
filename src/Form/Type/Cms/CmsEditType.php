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
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder->add('route', HiddenType::class);
        $builder->add('route_en', HiddenType::class);
        $builder->add('role', HiddenType::class);
        $builder->add('role_en', HiddenType::class);
        $builder->add('all_params', HiddenType::class);
        $builder->add('content', HiddenType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', CmsEditCommand::class);
    }
}