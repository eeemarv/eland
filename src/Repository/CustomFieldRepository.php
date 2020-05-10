<?php declare(strict_types=1);

namespace App\Repository;

use App\Service\Xdb;
use App\Service\Cache;
use Doctrine\DBAL\Connection as Db;

class CustomFieldRepository
{
	private $db;
	private $xdb;
	private $cache;

	private $data = [
		'eid'				=> '',
		'uid'				=> '',
		'gid'				=> '',
		'name'				=> [
			'nl'	=> '',
			'en'	=> '',
			'fr'	=> '',
		],
		'type'		=> '',
		'required'	=> false,
	];

	public function __construct(Db $db, Xdb $xdb, Cache $cache)
	{
		$this->db = $db;
		$this->xdb = $xdb;
		$this->cache = $cache;
	}

	public function syncToElas()
	{

	}

	public function syncToEland()
	{

	}
}
