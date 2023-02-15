<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use Redis;

class StaticContentService
{
	const PREFIX = 's_content_';
	const TTL = 518400; // 60 days

	protected $local_cache = [];

	public function __construct(
		protected Db $db,
		protected Redis $predis
	)
	{
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
			'columns'	=> [
				'lang',
				'block',
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
			$sql['columns'][] = 'role';
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
			$sql['columns'][] = 'route';
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
		SessionUserService $su,
		string $schema
	):void
	{
		if (substr($route, -6) === '_admin')
		{
			$route = substr($route, 0, strlen($route) - 6);
		}

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
			$sql_params = [$content, $su->id(), ...$sql['params']];
			$sql_types = [\PDO::PARAM_STR, \PDO::PARAM_INT, ...$sql['types']];

			$affected_rows = $this->db->executeStatement('update ' . $schema . '.s_content
				set content = ?, last_edit_by = ?
				where ' . $sql_where,
				$sql_params, $sql_types
			);

			if ($affected_rows === 0)
			{
				$sql_columns = ['content', 'last_edit_by', ...$sql['columns']];
				$insert_ary = array_combine($sql_columns, $sql_params);
				$insert_ary['created_by'] = $su->id();
				$sql_types[] = \PDO::PARAM_INT;

				$this->db->insert($schema . '.s_content',
					$insert_ary,
					$sql_types
				);
			}
		}

		$key = self::PREFIX . $schema;
		$field = $lang;
		$field .= $role === '' ? '' : '.' . $role;
		$field .= $route === '' ? '' : '.' . $route;
		$field .= '.' . $block;

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

		if (substr($route, -6) === '_admin')
		{
			$route = substr($route, 0, strlen($route) - 6);
		}

		$key = self::PREFIX . $schema;
		$field = $lang;
		$field .= $role === '' ? '' : '.' . $role;
		$field .= $route === '' ? '' : '.' . $route;
		$field .= '.' . $block;

		if (isset($this->local_cache[$field]))
		{
			return $this->local_cache[$field];
		}

		$str = $this->predis->hget($key, $field);

		if (is_string($str))
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
