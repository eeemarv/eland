<?php declare(strict_types=1);

namespace App\Form\Type\Mollie;

use App\Command\Mollie\MollieConfigModeCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MollieConfigModeType extends AbstractType
{
  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $has_webhook_key = $options['has_webhook_key'];
    $has_live_api_key = $options['has_live_api_key'];
    $has_test_api_key = $options['has_test_api_key'];

    $builder->add('mollie_mode', ChoiceType::class, [
      'label' => 'mollie_config.mode.label',
      'choices' => [
        'mollie_config.mode.none.label' => 'none',
        'mollie_config.mode.test.label' => 'test',
        'mollie_config.mode.live.label' => 'live',
      ],
      'expanded' => true,
      'multiple'  => false,
      'choice_attr' => function (mixed $choice, string $key, mixed $value)
        use ($has_webhook_key, $has_live_api_key, $has_test_api_key){
        $attr_ary = [
          'title' => 'mollie_config.mode.' . $value . '.title',
        ];
        if ($value === 'test'
          && (!$has_test_api_key || !$has_webhook_key))
        {
          $attr_ary['disabled'] = true;
        }
        if ($value === 'live'
          && (!$has_live_api_key || !$has_webhook_key))
        {
          $attr_ary['disabled'] = true;
        }
        return $attr_ary;
      },
    ]);

    if (!$has_webhook_key || !($has_live_api_key || $has_test_api_key))
    {
      $builder->setDisabled(true);
    }

    $builder->add('submit', SubmitType::class);
  }

  public function buildView(
    FormView $view,
    FormInterface $form,
    array $options
  ):void
  {
    parent::buildView($view, $form, $options);

    $has_webhook_key = $options['has_webhook_key'];
    $has_live_api_key = $options['has_live_api_key'];
    $has_test_api_key = $options['has_test_api_key'];

    $view->vars['has_webhook_key'] = $has_webhook_key;
    $view->vars['has_live_api_key'] = $has_live_api_key;
    $view->vars['has_test_api_key'] = $has_test_api_key;

    $view->vars['keys_missing'] = !$has_webhook_key
      || !($has_live_api_key || $has_test_api_key);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', MollieConfigModeCommand::class);
    $resolver->setDefault('has_webhook_key', false);
    $resolver->setDefault('has_live_api_key', false);
    $resolver->setDefault('has_test_api_key', false);
    $resolver->setAllowedTypes('has_webhook_key', 'bool');
    $resolver->setAllowedTypes('has_live_api_key', 'bool');
    $resolver->setAllowedTypes('has_test_api_key', 'bool');
  }
}