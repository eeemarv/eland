<?php declare(strict_types=1);

namespace App\Repository;

use App\Service\Xdb;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageRepository
{
	private $xdb;

	public function __construct(Xdb $xdb)
	{
		$this->xdb = $xdb;
	}

	public function get(string $id, string $schema):array
	{
		$data = $this->xdb->get('page', $id, $schema);
		
		if (!$data)
		{
			throw new NotFoundHttpException(sprintf('Page %s in schema %s does not exist in %s', 
				$id, $schema, __CLASS__));
		}

		return $data;
	}
}
