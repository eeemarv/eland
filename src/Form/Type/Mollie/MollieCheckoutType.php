<?php declare(strict_types=1);

namespace App\Form\Type\Mollie;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class MollieCheckoutType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder
            ->add('submit', SubmitType::class);
    }
}