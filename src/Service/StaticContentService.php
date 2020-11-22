<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use Predis\Client as Predis;

class StaticContentService
{
	const PREFIX = 's_content_';
	const TTL = 518400; // 60 days

	protected Db $db;
	protected Predis $predis;

	protected $local_cache = [];

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
		$this->local_cache = [];
		$this->predis->del(self::PREFIX . $schema);
	}

	private function get_sql(
		string $lang,
		string $role,
		string $route,
		string $block
	):array
	{
		$sql = [
			'where'	=> [
				'lang = ?',
				'block = ?',
			],
			'params' => [
				$lang,
				$block,
			],
			'types'	=> [
				\PDO::PARAM_STR,
				\PDO::PARAM_STR,
			],
		];

		if ($role === '')
		{
			$sql['where'][] = 'role is null';
		}
		else
		{
			$sql['where'][] = 'role = ?';
			$sql['params'][] = $role;
			$sql['types'][] = \PDO::PARAM_STR;
		}

		if ($route === '')
		{
			$sql['where'][] = 'route is null';
		}
		else
		{
			$sql['where'][] = 'route = ?';
			$sql['params'][] = $route;
			$sql['types'][] = \PDO::PARAM_STR;
		}

		return $sql;
	}

	public function set(
		string $role,
		string $route,
		string $block,
		string $content,
		string $schema
	):void
	{
		$current_content = $this->get($role, $route, $block, $schema);

		if ($current_content === $content)
		{
			return;
		}

		$lang = 'nl';

		$sql = $this->get_sql($lang, $role, $route, $block);
		$sql_where = implode(' and ', $sql['where']);

		if ($content === '')
		{
			$this->db->executeStatement('delete
				from ' . $schema . '.s_content
				where ' . $sql_where, $sql['params'], $sql['types']);
		}
		else
		{
			$sql_params = [$content, ...$sql['params']];
			$sql_types = [\PDO::PARAM_STR, ...$sql['types']];

			$this->db->executeStatement('update ' . $schema . '.s_content
				set content = ?
				from ' . $schema . '.s_content
				where ' . $sql_where,
				$sql_params, $sql_types
			);
		}

		$key = self::PREFIX . $schema;
		$field = $lang;
		$field .= $role === '' ? '' : '.' . $role;
		$field .= $route === '' ? '' : '.' . $route;
		$field .= $block;

		unset($this->local_cache[$field]);
		$this->predis->hdel($key, $field);

		return;
	}

	public function get(
		string $role,
		string $route,
		string $block,
		string $schema
	):string
	{
		$lang = 'nl';

		$key = self::PREFIX . $schema;
		$field = $lang;
		$field .= $role === '' ? '' : '.' . $role;
		$field .= $route === '' ? '' : '.' . $route;
		$field .= $block;

		if (isset($this->local_cache[$field]))
		{
			return $this->local_cache[$field];
		}

		$str = $this->predis->hget($key, $field);

		if (isset($str))
		{
			$this->local_cache[$field] = $str;
			return $str;
		}

		$sql = $this->get_sql($lang, $role, $route, $block);

		$sql_where = implode(' and ', $sql['where']);

		$content = $this->db->fetchOne('select content
			from ' . $schema . '.s_content
			where ' . $sql_where,
			$sql['params'], $sql['types']
		);

		if ($content === false)
		{
			$content = '';
		}

		$this->local_cache[$field] = $content;
		$this->predis->hset($key, $field, $content);
		return $content;
	}
}
