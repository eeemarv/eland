<?php declare(strict_types=1);

namespace App\Form\Post;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Form\Input\TextAddonType;
use App\Form\Input\NumberAddonType;
use App\Form\Typeahead\TypeaheadUserType;

class TransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('id_from', TypeaheadUserType::class, [
				'source_id'	=> 'form_id_to',		
			])
			->add('id_to', TypeaheadUserType::class, [
                'source_route'  => 'user_typeahead',
                'source_params' => [
                    'user_type'     => 'all',
                ],
			])
			->add('amount', NumberAddonType::class, [
				'constraints'	=> [
				],
			])
			->add('description', TextAddonType::class, [
				'constraints' 	=> [
					new Assert\NotBlank(),
					new Assert\Length(['max' => 60, 'min' => 1]),
				],
				'attr'	=> [
					'maxlength'	=> 60,
				],
			])
			->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
        ]);
    }
}
