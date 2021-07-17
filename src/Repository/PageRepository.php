<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function get(string $id, string $schema):array
	{
		/*
		$data = $this->xdb->get('page', $id, $schema);

		if (!$data)
		{
			throw new NotFoundHttpException(sprintf('Page %s in schema %s does not exist in %s',
				$id, $schema, __CLASS__));
		}

		return $data;
		*/
		return [];
	}
}
