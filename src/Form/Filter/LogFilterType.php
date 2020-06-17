<?php declare(strict_types=1);

namespace App\Form\Filter;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Form\Input\TextAddonType;
use App\Form\Typeahead\TypeaheadUserType;
use App\Form\Input\DatepickerType;

class LogFilterType extends AbstractType
{
    public function __construct()
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

    /*
		$filter = $app['form.factory']->createNamedBuilder('', FormType::class, [], [
            'csrf_protection'	=> false,
        ])
        ->setMethod('GET')
        ->add('q', TextType::class, ['required' => false])
        ->add('letscode', TextType::class, ['required' => false])
        ->add('type', TextType::class, ['required' => false])
        ->add('fdate', TextType::class, ['required' => false])
        ->add('tdate', TextType::class, ['required' => false])
        ->add('z', SubmitType::class)
        ->getForm();

    $filter->handleRequest($request);
    */


        $builder
            ->setMethod('GET')
			->add('q', TextAddonType::class, [
                'required' => false,
            ])
			->add('user', TypeaheadUserType::class, [
				'required' 		=> false,
				'source_id'		=> 'f_to_user',
			])
			->add('type', TypeaheadUserType::class, [
				'required' 		=> false,
                'source_route'  => 'user_typeahead',
                'source_params' => [
                    'user_type'     => 'direct',
                ],
			])
			->add('from_date', DatepickerType::class, [
                'required' => false,
                'attr'  => [
                    'data-date-default-view-date'   => '-1y',
                    'data-date-end-date'            => '0d',
                ],
            ])
			->add('to_date', DatepickerType::class, [
                'required' => false,
                'attr'  => [
                    'data-date-end-date'            => '0d',
                ],
            ])
			->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection'   => false,
            'etoken_enabled'    => false,
        ]);
    }
}