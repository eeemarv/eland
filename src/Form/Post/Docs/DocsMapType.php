<?php declare(strict_types=1);

namespace App\Form\Post\Docs;

use App\Command\Docs\DocsMapCommand;
use App\Form\Type\TypeaheadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocsMapType extends AbstractType
{
    public function __construct(
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name',TypeaheadType::class, [
                'add'           => 'doc_map_names',
                'render_omit'   => $options['render_omit'],
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'render_omit'   => '',
            'data_class'    => DocsMapCommand::class,
        ]);
    }
}