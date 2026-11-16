<?php declare(strict_types=1);

namespace App\Form\Type\Config;

use App\Cnst\ConfigCnst;
use App\Command\Config\ConfigLandingPageCommand;
use App\Command\Config\ConfigNotificationsCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigNotificationsType extends AbstractType
{
  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $builder->add('email_enabled',CheckboxType::class);
    $builder->add('webpush_enabled',CheckboxType::class);

    $hours = range(0, 22, 2);
    $hour_options = array_combine($hours, $hours);
    $days_of_month = range(1, 28);
    $days_of_month_options = array_combine($days_of_month, $days_of_month);

    $builder->add('daily_hour', ChoiceType::class, [
      'choices'   => $hour_options,
      'choice_translation_domain' => false,
    ]);

    $builder->add('weekly_day_of_week', ChoiceType::class, [
      'choices'   => [
        'day_of_week.mon' => 1,
        'day_of_week.tue' => 2,
        'day_of_week.wed' => 3,
        'day_of_week.thu' => 4,
        'day_of_week.fri' => 5,
        'day_of_week.sat' => 6,
        'day_of_week.sun' => 0,
      ],
    ]);
    $builder->add('weekly_hour', ChoiceType::class, [
      'choices'   => $hour_options,
      'choice_translation_domain' => false,
    ]);

    $builder->add('monthly_day_of_month', ChoiceType::class, [
      'choices'   => $days_of_month_options,
      'choice_translation_domain' => false,
    ]);
    $builder->add('monthly_hour', ChoiceType::class, [
      'choices'   => $hour_options,
      'choice_translation_domain' => false,
    ]);

    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', ConfigNotificationsCommand::class);
  }
}