<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;

class EmailSentRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function register(Schema $schema)
	{

	}
}
