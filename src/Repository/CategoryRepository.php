<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryRepository
{
	protected Db $db;

	public function __construct(Db $db)
	{
		$this->db = $db;
	}

	public function get_all(string $schema):array
	{
		throw new \Exception('Needs to be rewritten');

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
		$parent_label = '***';

		$st = $this->db->executeQuery('select count(m.*),
			c.name, c.id, c.parent_id, c.left_id, c.right_id
			from ' . $schema . '.categories c
			left join ' . $schema . '.messages m
				on m.category_id = c.id
			group by c.id
			order by c.left_id asc');

        while ($row = $st->fetch())
        {
			$parent_id = $row['parent_id'];
			$count = $row['count'];
			$name = $row['name'];
			$left_id = $row['left_id'];
			$right_id = $row['right_id'];
			$id = $row['id'];

			$label = $name;
			$label .= $count ? ' (' . $count . ')' : '';

			if (($left_id + 1) === $right_id)
			{
				if (isset($parent_id))
				{
					$choices[$parent_label][$label] = $id;
					continue;
				}
				$choices[$label] = $id;
				continue;
			}

			$parent_label = $label;
			$choices[$label] = [];
		}

		return $choices;
	}

	public function exists(int $id, string $schema):bool
	{
		throw new \Exception('Needs to be rewritten (maybe)');

		return $this->db->fetchColumn('select id
			from ' . $schema . '.categories
			where id = ?', [$id]) ? true : false;
	}

	///

    public function get(int $id, string $schema):array
    {
        $category = $this->db->fetchAssoc('select c.*, cp.name as parent_name
            from ' . $schema . '.categories c
            left join ' . $schema . '.categories cp
                on c.parent_id = cp.id
            where c.id = ?', [$id], [\PDO::PARAM_INT]);

        if (!$category)
        {
            throw new NotFoundHttpException('Category ' . $id . ' not found.');
        }

        return $category;
    }
}
