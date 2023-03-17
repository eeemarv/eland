<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;

class SystemRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}
}
