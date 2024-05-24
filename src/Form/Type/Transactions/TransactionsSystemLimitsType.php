<?php declare(strict_types=1);

namespace App\Form\Type\Transactions;

use App\Command\Transactions\TransactionsSystemLimitsCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionsSystemLimitsType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder
            ->add('min', IntegerType::class)
            ->add('max', IntegerType::class)
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefaults([
            'data_class'    => TransactionsSystemLimitsCommand::class,
        ]);
    }
}