<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryRepository
{
	protected $db;

	public function __construct(Db $db)
	{
		$this->db = $db;
	}

	public function get_all(string $schema):array
	{
		$categories = $this->db->fetchAll('select *
			from ' . $schema . '.categories
			order by fullname');

		$child_count_ary = [];

		foreach ($categories as $cat)
		{
			if (!isset($child_count_ary[$cat['id_parent']]))
			{
				$child_count_ary[$cat['id_parent']] = 0;
			}

			$child_count_ary[$cat['id_parent']]++;
		}

		foreach ($categories as &$cat)
		{
			if (isset($child_count_ary[$cat['id']]))
			{
				$cat['child_count'] = $child_count_ary[$cat['id']];
			}
		}

		return $categories;
	}

	public function get_all_choices(string $schema):array
	{
		$choices = [];

		$st = $this->db->executeQuery('select c.name,
			c.id, c.id_parent, count(m.id)
			from ' . $schema . '.categories c left join
				' . $schema . '.messages m on m.category_id = c.id
			group by c.id
			order by fullname');

        while ($row = $st->fetch())
        {
			if (!isset($row['id_parent']) || !$row['id_parent'])
			{
				if (isset($parent_name) && isset($sub_cats) && count($sub_cats))
				{
					$choices[$parent_name] = $sub_cats;
				}
				$parent_name = $row['name'];
				$sub_cats = [];
				continue;
			}

			$label = $row['name'];
			$label .= $row['count'] ? ' (' . $row['count'] . ')' : '';
			$sub_cats[$label] = (string) $row['id'];
		}

		if (isset($parent_name) && isset($sub_cats) && count($sub_cats))
		{
			$choices[$parent_name] = $sub_cats;
		}

		return $choices;
	}

	public function exists(int $id, string $schema):bool
	{
		return $this->db->fetchColumn('select id
			from ' . $schema . '.categories
			where id = ?', [$id]) ? true : false;
	}

	public function get(int $id, string $schema):array
	{
		$data = $this->db->fetchAssoc('select *
			from ' . $schema . '.categories
			where id = ?', [$id]);

		if (!$data)
		{
			throw new NotFoundHttpException('Category ' . $id . ' not found.');
        }

		return $data;
	}

	public function getCountAds(int $id, string $schema):int
	{
		return $this->db->fetchColumn('select count(*)
			from ' . $schema . '.categories
			where id_parent = ?', [$id]);
	}

	public function getCountSubcategories(int $id, string $schema):int
	{
		return $this->db->fetchColumn('select count(*)
			from ' . $schema . '.categories
			where id_parent = ?', [$id]);
	}

	public function delete(int $id, string $schema)
	{
		$this->db->delete($schema . '.categories', ['id' => $id]);
	}

	public function update(int $id, string $schema, array $data)
	{
		$this->db->update($schema . '.categories', $data, ['id' => $id]);
	}

	public function getName(int $id, string $schema):string
	{
		return $this->db->fetchColumn('select name
			from ' . $schema . '.categories
			where id = ?', [$id]);
	}

	public function insert(string $schema, array $data)
	{
		$this->db->insert($schema . '.categories', $data);
	}
}
