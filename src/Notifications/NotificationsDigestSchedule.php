<?php declare(strict_types=1);

namespace App\Notifications;

use App\DTO\Schema;
use App\Notifications\DailyDigest\NotificationsDailyDigestMessage;
use App\Notifications\MonthlyDigest\NotificationsMonthlyDigestMessage;
use App\Notifications\WeeklyDigest\NotificationsWeeklyDigestMessage;
use App\Service\ConfigService;
use App\Service\FeaturesService;
use App\Service\SystemsService;
use DateTimeZone;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class NotificationsDigestSchedule implements ScheduleProviderInterface
{
  public function __construct(
    private readonly SystemsService $systems_service,
    private readonly ConfigService $config_service,
    private readonly FeaturesService $features_service,
    private readonly LockFactory $lock_factory,
    private readonly CacheInterface $cache,
  )
  {
  }

  public function getSchedule(): Schedule
  {
    $schedule = new Schedule();
    $schedule->lock(
      $this->lock_factory->createLock(
        resource: 'notifications_digest_schedule',
      )
    );
    $schedule->stateful($this->cache);
  
    $all_systems = $this->systems_service->get_all();
    $schemas = array_keys($all_systems);

    foreach ($schemas as $schema) 
    {
      $schema_o = new Schema($schema);
      $email_enabled = $this->config_service->get_bool(
        config_id: 'notifications.email.enabled',
        schema: $schema_o,
      );
      if (!$email_enabled)
      {
        continue;
      }

      $daily_hour = $this->features_service->get_int(
        feature_id: 'notifications.daily.hour',
        schema: $schema_o,
      );

      if (isset($daily_hour))
      {
        $expr = sprintf('# %d * * *', $daily_hour);
        error_log('** Daily: ' . $expr . ' :: ' . $schema);
        $schedule->add(RecurringMessage::cron(
          expression: $expr,
          message: new NotificationsDailyDigestMessage(
            schema: $schema_o,
          ),
          // TODO configurable timezone
          timezone: new DateTimeZone('Europe/Brussels'),
        ));
      }

      $weekly_hour = $this->features_service->get_int(
        feature_id: 'notifications.weekly.hour',
        schema: $schema_o,
      );
      $weekly_day_of_week = $this->features_service->get_int(
        feature_id: 'notifications.weekly.day_of_week',
        schema: $schema_o,
      );

      if (isset($weekly_hour) && isset($weekly_day_of_week))
      {
        $expr = sprintf('# %d * * %d', $weekly_hour, $weekly_day_of_week);
        error_log('** Weekly: ' . $expr . ' :: ' . $schema);
        $schedule->add(RecurringMessage::cron(
          expression: $expr,
          message: new NotificationsWeeklyDigestMessage(
            schema: $schema_o,
          ),
          // TODO configurable timezone
          timezone: new DateTimeZone('Europe/Brussels'),
        ));
      }

      $monthly_hour = $this->features_service->get_int(
        feature_id: 'notifications.monthly.hour',
        schema: $schema_o,
      );
      $monthly_day_of_month = $this->features_service->get_int(
        feature_id: 'notifications.monthly.day_of_month',
        schema: $schema_o,
      );

      if (isset($monthly_hour) && isset($monthly_day_of_month))
      {
        $expr = sprintf('# %d %d * *', $monthly_hour, $monthly_day_of_month);
        error_log('** Montly: ' . $expr . ' :: ' . $schema);
        $schedule->add(RecurringMessage::cron(
          expression: $expr,
          message: new NotificationsMonthlyDigestMessage(
            schema: $schema_o,
          ),
          // TODO configurable timezone
          timezone: new DateTimeZone('Europe/Brussels'),
        ));
      }
    }

    return $schedule;
  }
}
