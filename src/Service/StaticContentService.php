<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Predis\Client as Predis;
use Symfony\Component\Validator\Exception\LogicException;

class StaticContentService
{
	const PREFIX = 'static_content_';
	const TTL= 518400; // 60 days

	protected Db $db;
	protected Predis $predis;

	public function __construct(
		Db $db,
		Predis $predis
	)
	{
		$this->predis = $predis;
		$this->db = $db;
	}

	public function clear_cache(string $schema):void
	{
		$lang_ary = ['nl'];

		foreach($lang_ary as $lang)
		{
			$this->predis->del(self::PREFIX . $lang . '_' . $schema);
		}
	}

	public function set(string $id, string $block, string $value, string $schema):void
	{
		$lang = 'nl';

		if (!preg_match('/^[a-z_]+$/', $block))
		{
			throw new LogicException('Unacceptable block');
		}

		$this->db->executeStatement('update ' . $schema . '.static_content
			set data = jsonb_set(data, \'{' . $block . '}\',  ?)
			where id = ? and lang = ?',
			[$value, $id, $lang],
			[Types::JSON, \PDO::PARAM_STR, \PDO::PARAM_STR]
		);

		$this->clear_cache($schema);
		return;
	}

	public function get(string $id, string $block, string $schema):string
	{
		$lang = 'nl';
		$key = self::PREFIX . $lang . '_' . $schema;
		$str = $this->predis->hget($key, $id . '.' . $block);

		if (isset($str))
		{
			return $str;
		}

		$data_json = $this->db->fetchOne('select data
			from ' . $schema . '.static_content
			where id = ? and lang = ?',
			[$id, $lang],
			[\PDO::PARAM_STR, \PDO::PARAM_STR]);

		$data = json_decode($data_json, true);

		foreach($data as $data_block => $data_str)
		{
			$this->predis->hset($key, $id . '.' . $data_block, $data_str);
		}

		$this->predis->expire($key, self::TTL);

		return $data[$block] ?? '';
	}
}
