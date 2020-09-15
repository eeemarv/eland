<?php declare(strict_types=1);

namespace App\Repository;

use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryRepository
{
	protected Db $db;

	public function __construct(Db $db)
	{
		$this->db = $db;
	}

	public function insert(string $name, SessionUserService $su, string $schema):int
	{
		$created_by = $su->is_master() ? null : $su->id();

		$this->db->executeUpdate('insert into ' . $schema . '.categories (name, created_by, level, left_id, right_id)
			select ?, ?, 1, coalesce(max(right_id), 0) + 1, coalesce(max(right_id), 0) + 2
			from ' . $schema . '.categories',
			[$name, $created_by],
			[\PDO::PARAM_STR, \PDO::PARAM_INT]);

		return (int) $this->db->lastInsertId($schema . '.categories_id_seq');
	}

	public function update_name(int $id, string $name, string $schema):bool
	{
		return $this->db->update($schema . '.categories', ['name' => $name], ['id' => $id]) ? true : false;
	}

	public function del(int $id, string $schema):bool
	{
		$this->db->beginTransaction();
		$this->db->executeUpdate('update ' . $schema . '.categories
			set left_id = left_id - 2
			where left_id > (select left_id
				from ' . $schema . '.categories
				where id = ?)', [$id], [\PDO::PARAM_INT]);
		$this->db->executeUpdate('update ' . $schema . '.categories
			set right_id = right_id - 2
			where right_id > (select right_id
				from ' . $schema . '.categories
				where id = ?)', [$id], [\PDO::PARAM_INT]);
		$this->db->delete($schema . '.categories', ['id' => $id]);
		return $this->db->commit();
	}

	public function is_unique_name_except_id(
		string $name, int $id, string $schema
	):bool
	{
		$lowercase_name = trim(strtolower($name));

		return $this->db->fetchColumn('select id
			from ' . $schema . '.categories
			where id <> ? and lower(name) = ?',
			[$id, $lowercase_name],
			0,
			[\PDO::PARAM_INT, \PDO::PARAM_STR]) ? false : true;
	}

	public function get_flat_ary(string $schema):array
	{
        $categories = [];

        $stmt = $this->db->prepare('select c.*, count(m.*)
            from ' . $schema . '.categories c
            left join ' . $schema . '.messages m
            on m.category_id = c.id
            group by c.id
            order by c.left_id asc');

        $stmt->execute();

        while ($row = $stmt->fetch())
        {
            $categories[$row['id']] = $row;
		}

		return $categories;
	}

	public function update_list(array $posted_ary, string $schema):int
	{
		$update_ary = [];
		$count_posted = 0;
		$left_id = 0;

		$this->db->beginTransaction();
        $stored_ary = $this->get_flat_ary($schema);

        foreach ($posted_ary as $base_item)
        {
            if (!isset($base_item['id']))
            {
                throw new BadRequestHttpException('Malformed request for categories input (missing id): ' . json_encode($posted_ary));
            }

			$left_id++;
            $count_posted++;
            $base_id = $base_item['id'];
            $children_count = count($base_item['children'] ?? []);

            if ($children_count > 0 && $stored_ary[$base_id]['count'] > 0)
            {
                throw new BadRequestHttpException('A category with messages cannot contain sub-categories. id: ' . $base_id);
            }

            if (!isset($stored_ary[$base_id]))
            {
                throw new BadRequestHttpException('Category with id ' . $base_id . ' not found.');
			}

			$right_id = $left_id + ($children_count * 2) + 1;

			$update_ary[$base_id] = [
				'left_id'   => $left_id,
				'right_id'  => $right_id,
				'level'     => 1,
				'parent_id' => null,
			];

			$left_id++;

            if (isset($base_item['children']) && count($base_item['children']))
            {
                foreach($base_item['children'] as $sub_item)
                {
                    $count_posted++;

                    if (!isset($sub_item['id']))
                    {
                        throw new BadRequestHttpException('Malformed request for categories input (missing id): ' . json_encode($posted_ary));
                    }

                    $sub_id = $sub_item['id'];

                    if (!isset($stored_ary[$sub_id]))
                    {
                        throw new BadRequestHttpException('Category with id ' . $sub_id . ' not found.');
                    }

                    if (isset($sub_item['children']))
                    {
                        throw new BadRequestHttpException('A subcategory can not have subcategories itself. id: ' . $sub_id);
					}

					$right_id = $left_id + 1;

					$update_ary[$sub_id] = [
						'left_id'   => $left_id,
						'right_id'  => $right_id,
						'level'     => 2,
						'parent_id' => $base_id,
					];

					$left_id = $right_id + 1;
                }
			}
		}

		if (count($update_ary) !== count($stored_ary))
		{
			throw new BadRequestHttpException('Update Category count (' . count($update_ary) . ') to stored count (' . count($stored_ary) . ') mismatch.');
		}

		$count_updated = 0;

		foreach ($update_ary as $id => $update)
		{
			$stored_cat = $stored_ary[$id];

			if ($stored_cat['level'] === $update['level']
				&& $stored_cat['parent_id'] === $update['parent_id']
				&& $stored_cat['left_id'] === $update['left_id']
				&& $stored_cat['right_id'] === $update['right_id'])
			{
				continue;
			}

			$this->db->update($schema . '.categories', $update, ['id' => $id]);
			$count_updated++;
		}
		$this->db->commit();

		return $count_updated;
	}

	public function get_list_and_input_ary(string $schema):array
	{
        $categories = [];
        $input_ary = [];
        $base_cat_index = -1;

        $stmt = $this->db->prepare('select c.*, count(m.*)
            from ' . $schema . '.categories c
            left join ' . $schema . '.messages m
            on m.category_id = c.id
            group by c.id
            order by c.left_id asc');

        $stmt->execute();

        while ($row = $stmt->fetch())
        {
            $id = $row['id'];
            $level = $row['level'];

            $categories[$id] = $row;

            if ($level === 1)
            {
                $base_cat_index++;
                $input_ary[$base_cat_index] = ['id' => $id];
                continue;
            }

            if (!isset($input_ary[$base_cat_index]['children']))
            {
                $input_ary[$base_cat_index]['children'] = [];
            }

            $input_ary[$base_cat_index]['children'][] = ['id' => $id];
		}

		return [
			'categories'	=> $categories,
			'input_ary'		=> $input_ary,
		];
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

    public function get_with_messages_count(int $id, string $schema):array
    {
		$category = $this->db->fetchAssoc('select c.*,
				cp.name as parent_name, count(m.*)
            from ' . $schema . '.categories c
            left join ' . $schema . '.categories cp
				on c.parent_id = cp.id
			left join ' . $schema . '.messages m
				on m.category_id = c.id
			where c.id = ?
			group by c.id, cp.name', [$id], [\PDO::PARAM_INT]);

        if (!$category)
        {
            throw new NotFoundHttpException('Category ' . $id . ' not found.');
        }

        return $category;
    }
}
