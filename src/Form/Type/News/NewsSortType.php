<?php declare(strict_types=1);

namespace App\Form\Type\News;

use App\Command\News\NewsSortCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewsSortType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('sort_asc', CheckboxType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', NewsSortCommand::class);
    }
}