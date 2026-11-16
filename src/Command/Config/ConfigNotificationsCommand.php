<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Attributes\FeaturesMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;

class ConfigNotificationsCommand implements CommandInterface
{
  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'notifications.email.enabled')]
  public mixed $email_enabled;

  #[Type(type: 'bool')]
  #[ConfigMap(type: 'bool', key: 'notifications.webpush.enabled')]
  public mixed $webpush_enabled;

  #[NotNull()]
  #[Range(min: 0, max: 23)]
  #[FeaturesMap(type: 'int', key: 'notifications.daily.hour')]
  public mixed $daily_hour;

  #[NotNull()]
  #[Range(min: 0, max: 23)]
  #[FeaturesMap(type: 'int', key: 'notifications.weekly.hour')]
  public mixed $weekly_hour;

  #[NotNull()]
  #[Range(min: 0, max: 23)]
  #[FeaturesMap(type: 'int', key: 'notifications.weekly.day_of_week')]
  public mixed $weekly_day_of_week;

  #[NotNull()]
  #[Range(min: 0, max: 23)]
  #[FeaturesMap(type: 'int', key: 'notifications.monthly.hour')]
  public mixed $monthly_hour;

  #[NotNull()]
  #[Range(min: 1, max: 28)]
  #[FeaturesMap(type: 'int', key: 'notifications.monthly.day_of_month')]
  public mixed $monthly_day_of_month;
}
