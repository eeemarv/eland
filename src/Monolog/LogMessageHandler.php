<?php declare(strict_types=1);

namespace App\Monolog;

use App\DTO\Schema;
use App\Repository\LogRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler()]
class LogMessageHandler
{
  public function __construct(
    private readonly LogRepository $log_repository,
  )
  {
  }

  public function __invoke(
    LogMessage $log_message
  ):void
  {
    $record = $log_message->record;
    $context = $record['context'];
    $extra = $record['extra'];

    if (isset($context['schema']))
    {
      $schema = $context['schema'];

      if (!$schema)
      {
        return;
      }
    }
    else
    {
      if (!isset($extra['schema']))
      {
        return;
      }

      $schema = $extra['schema'];
    }

    if (!$schema)
    {
      return;
    }

    $user_schema = $schema;

    if (isset($extra['os'])
      && $extra['os'])
    {
      $org_schema = $extra['os'];

      if ($org_schema)
      {
        $user_schema = $org_schema;
      }
    }

    $user_id = null;
    $is_master = false;
    $ip = null;

    if (isset($extra['logins'])
      && isset($extra['logins'][$user_schema]))
    {
      if ($extra['logins'][$user_schema] === 'master')
      {
        $is_master = true;
      }
      else
      {
        $user_id = $extra['logins'][$user_schema];        
      }
    }

    if (isset($extra['ip']))
    {
      $ip = $extra['ip'];
    }

    $this->log_repository->insert(
      type: $record['level_name'],
      event: $record['message'],
      data: $record,
      ip: $ip,
      user_id: $user_id,
      user_schema: $user_schema,
      is_master: $is_master,
      schema: new Schema($schema),
    );
  }
}
