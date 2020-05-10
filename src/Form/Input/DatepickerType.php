<?php declare(strict_types=1);

namespace App\Form\Input;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

use App\Form\Input\TextAddonType;
use App\Form\DataTransformer\DatepickerTransformer;

class DatepickerType extends AbstractType
{
    private $transformer;
    
    public function __construct(DatepickerTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer($this->transformer);
    }
/*
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('abbrev', TextType::class, [			
            'constraints' 	=> [
                new Assert\NotBlank(),
                new Assert\Length(['max' => 10, 'min' => 1]),
                new unique_in_column([
                    'db'        => $options['db'],
                    'schema'    => $options['schema'],
                    'table'     => 'type_contact',
                    'column'    => 'abbrev',
                    'ignore'    => $options['ignore'],
                ]),
            ],
                'attr'	=> [
                    'maxlength'	=> 10,
                ],
            ])

            ->add('name', TextType::class, [			
            'constraints' 	=> [
                new Assert\NotBlank(),
                new Assert\Length(['max' => 20, 'min' => 1]),
            ],
                'attr'	=> [
                    'maxlength'	=> 20,
                ],
            ])    

            ->add('submit', SubmitType::class);
    }
    */

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        /*
        if (isset($options['fa'])) 
        {
            $view->vars['fa'] = $options['fa'];
        }

        if (isset($options['addon_label'])) 
        {
            $view->vars['addon_label'] = $options['addon_label'];
        }
        */
    }    

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'schema'        => null,
        ]);
    }

    public function getParent()
    {
        return TextAddonType::class;
    }

    public function getBlockPrefix()
    {
        return 'datepicker';
    }
}