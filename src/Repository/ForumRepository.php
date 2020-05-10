<?php declare(strict_types=1);

namespace App\Repository;

use App\Service\Xdb;
use App\Service\Pagination;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ForumRepository
{
	private $xdb;

	public function __construct(Xdb $xdb)
	{
		$this->xdb = $xdb;
	}

	public function get_all(Pagination $pagination, string $schema):array
	{

	}

	public function get(string $id, string $schema):array
	{
		$data = $this->xdb->get('forum', $id, $schema);
		
		if (!$data)
		{
			throw new NotFoundHttpException(sprintf('Forum %s does not exist in %s', 
				$id, __CLASS__));
		}

		return $data;
	}
}
