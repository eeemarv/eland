<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CategoryRepository
{
	public function __construct(
    private readonly Db $db
  )
	{
  }

	public function insert(
    string $name,
    int|null $created_by,
    Schema $schema,
  ):int
	{
		$this->db->executeStatement('insert into ' . $schema->str() . '.categories
      (name, created_by, level, left_id, right_id)
			select :name, :created_by, 1,
      coalesce(max(right_id), 0) + 1,
      coalesce(max(right_id), 0) + 2
			from ' . $schema->str() . '.categories', [
        'name'  => $name,
        'created_by'  => $created_by,
      ], [
        'name'  => Types::STRING,
        'created_by'  => Types::INTEGER,
      ]);

		return (int) $this->db->lastInsertId($schema->str() . '.categories_id_seq');
	}

	public function update_name(
    int $id,
    string $name,
    Schema $schema,
  ):bool
	{
		return $this->db->update($schema->str() . '.categories', [
      'name' => $name,
    ], [
      'id' => $id,
    ], [
      'name'  => Types::STRING,
      'id'  => Types::INTEGER,
    ]) ? true : false;
	}

	public function del(
    int $id,
    Schema $schema,
  ):bool
	{
		$this->db->beginTransaction();
		$this->db->executeStatement('update ' . $schema->str() . '.categories
			set left_id = left_id - 2
			where left_id > (select left_id
				from ' . $schema->str() . '.categories
				where id = :id)', [
        'id'  => $id,
      ], [
        'id'  => Types::INTEGER,
      ]);
		$this->db->executeStatement('update ' . $schema->str() . '.categories
			set right_id = right_id - 2
			where right_id > (select right_id
				from ' . $schema->str() . '.categories
				where id = :id)', [
        'id'  => $id,
      ], [
        'id'  => Types::INTEGER,
      ]);
		$this->db->delete($schema->str() . '.categories', [
      'id' => $id,
    ], [
      'id'  => Types::INTEGER,
    ]);
		return $this->db->commit();
	}

	public function is_unique_name_except_id(
		string $name,
    int $id,
    Schema $schema,
	):bool
	{
		$lower_name = trim(strtolower($name));

		$stmt = $this->db->prepare('select id
			from ' . $schema->str() . '.categories
			where id <> :id
				and lower(name) = :lower_name');
		$stmt->bindValue('id', $id, Types::INTEGER);
		$stmt->bindValue('lower_name', $lower_name, Types::STRING);
		$res = $stmt->executeQuery();
		return $res->fetchOne() === false;
	}

	public function get_flat_ary(
    Schema $schema,
  ):array
	{
    $categories = [];

    $stmt = $this->db->prepare('select c.*, count(m.*)
      from ' . $schema->str() . '.categories c
      left join ' . $schema->str() . '.messages m
      on m.category_id = c.id
      group by c.id
      order by c.left_id asc');

    $res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
    {
      $categories[$row['id']] = $row;
		}

		return $categories;
	}

	public function update_list(
    array $posted_ary,
    Schema $schema,
  ):int
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

			$this->db->update($schema->str() . '.categories',
        $update, [
          'id' => $id,
        ], [
          'level' => Types::INTEGER,
          'parent_id' => Types::INTEGER,
          'left_id' => Types::INTEGER,
          'right_id'  => Types::INTEGER,
        ]);
			$count_updated++;
		}
		$this->db->commit();

		return $count_updated;
	}

	public function get_list_and_input_ary(
    Schema $schema,
  ):array
	{
    $categories = [];
    $input_ary = [];
    $base_cat_index = -1;

    $stmt = $this->db->prepare('select c.*, count(m.*)
      from ' . $schema->str() . '.categories c
      left join ' . $schema->str() . '.messages m
      on m.category_id = c.id
      group by c.id
      order by c.left_id asc');

    $res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
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

	public function get_all_choices(
    Schema $schema,
  ):array
	{
		$choices = [];
		$parent_label = '***';

		$res = $this->db->executeQuery('select count(m.*),
			c.name, c.id, c.parent_id, c.left_id, c.right_id
			from ' . $schema->str() . '.categories c
			left join ' . $schema->str() . '.messages m
				on m.category_id = c.id
			group by c.id
			order by c.left_id asc');

    while ($row = $res->fetchAssociative())
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

	function get_all(
    Schema $schema,
  ):array
	{
		return $this->db->fetchAllAssociative('select *
			from ' . $schema->str() . '.categories
			order by left_id asc') ?: [];
	}

  public function get(
    int $id,
    Schema $schema,
  ):array|false
  {
    $category = $this->db->fetchAssociative('select c.*, cp.name as parent_name
      from ' . $schema->str() . '.categories c
      left join ' . $schema->str() . '.categories cp
          on c.parent_id = cp.id
      where c.id = :id', [
        'id'  => $id,
      ], [
        'id'  => Types::INTEGER,
      ]);

    return $category;
	}

  public function get_with_messages_count(
    int $id,
    Schema $schema,
  ):array|false
  {
		$category = $this->db->fetchAssociative('select c.*,
			cp.name as parent_name, count(m.*)
      from ' . $schema->str() . '.categories c
      left join ' . $schema->str() . '.categories cp
				on c.parent_id = cp.id
			left join ' . $schema->str() . '.messages m
				on m.category_id = c.id
			where c.id = :id
			group by c.id, cp.name', [
        'id'  => $id,
      ], [
        'id'  => Types::INTEGER,
      ]);

    return $category;
  }
}
