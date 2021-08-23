<?php declare(strict_types=1);

namespace App\Form\Filter;

use App\Form\Type\DatepickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\Type\TypeaheadType;

class TransactionFilterType extends AbstractType
{
    public function __construct()
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->setMethod('GET')
			->add('q', TextType::class, [
                'required' => false,
            ])
			->add('from_user', TypeaheadType::class, [
				'filter'		=> 'accounts',
                'required'      => false,
			])
			->add('to_user', TypeaheadType::class, [
                'filter'        => 'accounts',
				'required' 		=> false,
			])
			->add('andor', ChoiceType::class, [
				'required' 	=> true,
				'choices'	=> [
					'and'	=> 'and',
					'or'	=> 'or',
					'nor'	=> 'nor',
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
                'attr'  => [
                    'data-date-end-date'            => '0d',
                ],
                'required'  => false,
            ])
			->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection'       => false,
            'form_token_enabled'    => false,
        ]);
    }
}