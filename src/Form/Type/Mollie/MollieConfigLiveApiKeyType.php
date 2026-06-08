<?php declare(strict_types=1);

namespace App\Form\Type\Mollie;

use App\Command\Mollie\MollieConfigLiveApiKeyCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MollieConfigLiveApiKeyType extends AbstractType
{
  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $builder->add('live_api_key', TextType::class);
    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', MollieConfigLiveApiKeyCommand::class);
  }
}