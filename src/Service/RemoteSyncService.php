<?php declare(strict_types=1);

namespace App\Service;

use App\DTO\Schema;
use App\Repository\UserRepository;

class RemoteSyncService
{
	public function __construct(
    private readonly SystemsService $systems_service,
    private readonly ConfigService $config_service,
    private readonly UserRepository $user_repository,
	)
	{
	}

  /**
   * Syncs the name of a system (schema) to all other
   * systems user accounts that have matching remote_schema
   * set. Whan the name is in use, a suffix is added.
   */
  public function sync(
    Schema $schema,
  )
  {
    $name = $this->config_service->get_str(
      config_id: 'system.name', 
      schema: $schema,
    );

    $all_schemas = $this->systems_service->get_all();

    foreach ($all_schemas as $o_schema => $linked_intersystems)
    {



    }
  }


}
