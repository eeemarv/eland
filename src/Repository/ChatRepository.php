<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class ChatRepository
{
	public function __construct(
    private readonly Db $db
  )
	{
	}


}
