<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;

class PageRepository
{
	public function __construct(
		private readonly Db $db,
	)
	{
	}

	public function get(
    string $id,
    string $schema,
  ):array|false
	{
		/*
		$data = $this->xdb->get('page', $id, $schema);


		return $data;
		*/
		return [];
	}
}
