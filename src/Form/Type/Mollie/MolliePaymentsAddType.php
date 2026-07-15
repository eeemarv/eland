<?php declare(strict_types=1);

namespace App\Form\Type\Mollie;

use App\Command\Mollie\MolliePaymentsAddCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MolliePaymentsAddType extends AbstractType
{
  public function __construct(
  )
  {
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {

    $builder->add('amounts', CollectionType::class, [
      'entry_type'  => MoneyType::class,
      'entry_options'  => [
        'required'  => false,
        'currency'  => false,
        // symfony 8.x: 'input' => 'string',
      ],
    ]);

    $builder->add('description', TextType::class);

    $builder->add('verify', CheckboxType::class);

    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', MolliePaymentsAddCommand::class);
  }
}