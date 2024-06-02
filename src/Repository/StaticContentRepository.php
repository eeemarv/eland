<?php declare(strict_types=1);

namespace App\Repository;

use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;

class StaticContentRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	private function get_sql(
		string $lang,
		null|string $role,
		null|string $route,
		null|int $page_id,
		null|string $block
	):array
	{
		$sql = [
			'where'	=> [
				'lang = ?',
			],
			'columns'	=> [
				'lang',
			],
			'params' => [
				$lang,
			],
			'types'	=> [
				\PDO::PARAM_STR,
			],
		];

		if (isset($role))
		{
			$sql['where'][] = 'role = ?';
			$sql['columns'][] = 'role';
			$sql['params'][] = $role;
			$sql['types'][] = \PDO::PARAM_STR;
		}
		else
		{
			$sql['where'][] = 'role is null';
		}

		if (isset($route))
		{
			$sql['where'][] = 'route = ?';
			$sql['columns'][] = 'route';
			$sql['params'][] = $route;
			$sql['types'][] = \PDO::PARAM_STR;
		}
		else
		{
			$sql['where'][] = 'route is null';
		}

		if (isset($page_id))
		{
			$sql['where'][] = 'page_id = ?';
			$sql['columns'][] = 'page_id';
			$sql['params'][] = $page_id;
			$sql['types'][] = \PDO::PARAM_INT;
		}
		else
		{
			$sql['where'][] = 'page_id is null';
		}

		if (isset($block))
		{
			$sql['where'][] = 'block = ?';
			$sql['columns'][] = 'block';
			$sql['params'][] = $block;
			$sql['types'][] = \PDO::PARAM_STR;
		}

		return $sql;
	}

	public function set_content_block(
		string $lang,
		null|string $role,
		null|string $route,
		null|int $page_id,
		string $block,
		string $content,
		SessionUserService $su,
		string $schema
	):void
	{
		$sql = $this->get_sql($lang, $role, $route, $page_id, $block);

		$sql_where = implode(' and ', $sql['where']);

		if ($content === '')
		{
			$this->db->executeStatement('delete
				from ' . $schema . '.s_content
				where ' . $sql_where, $sql['params'], $sql['types']);

			return;
		}

		$sql_params = [$content, $su->id(), ...$sql['params']];
		$sql_types = [\PDO::PARAM_STR, \PDO::PARAM_INT, ...$sql['types']];

		$affected_rows = $this->db->executeStatement('update ' . $schema . '.s_content
			set content = ?, last_edit_by = ?
			where ' . $sql_where,
			$sql_params, $sql_types
		);

		if ($affected_rows !== 0)
		{
			return;
		}

		$sql_columns = ['content', 'last_edit_by', ...$sql['columns']];
		$insert_ary = array_combine($sql_columns, $sql_params);
		$insert_ary['created_by'] = $su->id();
		$sql_types[] = \PDO::PARAM_INT;

		$this->db->insert($schema . '.s_content',
			$insert_ary,
			$sql_types
		);

		return;
	}

	public function get_content_block_ary(
		string $lang,
		null|string $role,
		null|string $route,
		null|int $page_id,
		string $schema
	):array
	{
		$sql = $this->get_sql($lang, $role, $route, $page_id, null);

		$sql_where = implode(' and ', $sql['where']);

		$block_ary = [];

		$res = $this->db->executeQuery('select content, block
			from ' . $schema . '.s_content
			where ' . $sql_where,
			$sql['params'], $sql['types']
		);

		while ($row = $res->fetchAssociative())
		{
			$block_ary[$row['block']] = $row['content'];
		}

		return $block_ary;
	}
}
