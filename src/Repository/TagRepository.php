<?php declare(strict_types=1);

namespace App\Repository;

use App\Command\Tags\TagsDefCommand;
use Doctrine\DBAL\Connection as Db;
use Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TagRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function insert_tag(
		TagsDefCommand $command,
		string $schema
	):int
	{
		$data_ary = (array) $command;
		unset($data_ary['id']);
		$this->db->insert($schema . '.tags', $data_ary);
		return (int) $this->db->lastInsertId($schema . '.type_contact_id_seq');
	}

	public function update_tag(
		TagsDefCommand $command,
		string $schema
	):void
	{
		if (!isset($command->id))
		{
			throw new Exception('no id set for tag update.');
		}

		$id = $command->id;

		$this->db->update($schema . '.tags',
			(array) $command,
			['id' => $id]
		);
	}

	public function del_tag(
		int $id,
		string $schema
	):bool
	{
		return $this->db->delete($schema . '.tags',
			['id' => $id]) ? true : false;
	}

	public function get(
		int $id,
		string $tag_type,
		string $schema
	):array
	{
		$stmt = $this->db->prepare('select *
			from ' . $schema . '.tags
			where id = :id
				and tag_type = :tag_type');
		$stmt->bindValue('id', $id, \PDO::PARAM_INT);
		$stmt->bindValue('tag_type', $tag_type, \PDO::PARAM_STR);
		$res = $stmt->executeQuery();
		$tag =  $res->fetchAssociative();

		if ($tag === false)
		{
			throw new NotFoundHttpException('Tag with id ' . $id . ' and type ' . $tag_type . ' not found.');
		}

		return $tag;
	}

	public function get_with_count(
		int $id,
		string $tag_type,
		string $schema
	):array
	{
		$stmt = $this->db->prepare('select t.*, count(j.*)
			from ' . $schema . '.tags t
				left join ' . $schema . '.' . $tag_type . '_tags j
				on t.id = j.tag_id
			where t.id = :id
				and t.tag_type = :tag_type
			group by t.id');

		$stmt->bindValue('id', $id, \PDO::PARAM_INT);
		$stmt->bindValue('tag_type', $tag_type, \PDO::PARAM_STR);
		$res = $stmt->executeQuery();
		$tag = $res->fetchAssociative();

		if ($tag === false)
		{
			throw new NotFoundHttpException('Tag with id ' . $id . ' and type ' . $tag_type . ' not found.');
		}

		return $tag;
	}

	public function get_all_with_count(
		string $tag_type,
		string $schema
	):array
	{
		$stmt = $this->db->prepare('select t.*, count(j.*)
			from ' . $schema . '.tags t
			left join ' . $schema . '.' . $tag_type . '_tags j
			on t.id = j.tag_id
			where t.tag_type = :tag_type
			group by t.id
			order by t.pos asc;');
		$stmt->bindValue('tag_type', $tag_type, \PDO::PARAM_STR);
		$res = $stmt->executeQuery();
		return $res->fetchAllAssociative();
	}

	public function get_all_for_render(
		string $tag_type,
		string $schema
	):array
	{
		$tags = [];

        $stmt = $this->db->prepare('select id, txt, txt_color, bg_color, pos
            from ' . $schema . '.tags
            where tag_type = ?
			order by pos asc');

        $stmt->bindValue(1, $tag_type, \PDO::PARAM_STR);

        $res = $stmt->executeQuery();

        while ($row = $res->fetchAssociative())
        {
            $tags[] = $row;
        }

		return $tags;
	}

	public function get_flat_ary(
		string $tag_type,
		string $schema
	):array
	{
		$tags = [];

        $stmt = $this->db->prepare('select txt
            from ' . $schema . '.tags
            where tag_type = :tag_type
			order by pos');
        $stmt->bindValue('tag_type', $tag_type, \PDO::PARAM_STR);
        $res = $stmt->executeQuery();

        while ($row = $res->fetchAssociative())
        {
            $tags[] = $row['txt'];
        }

		return $tags;
	}

	public function insert(
		TagsDefCommand $command,
		int $created_by,
		string $schema
	):int
	{
		$stmt = $this->db->prepare('insert into ' . $schema . '.tags
			(txt, txt_color, bg_color, description, tag_type, created_by, pos)
			values(:txt, :txt_color, :bg_color, :description, :tag_type, :created_by, (
				select coalesce(max(pos), 0) + 1 from ' . $schema . '.tags
				where tag_type = :tag_type
			))');
		$stmt->bindValue('txt', $command->txt, \PDO::PARAM_STR);
		$stmt->bindValue('txt_color', $command->txt_color, \PDO::PARAM_STR);
		$stmt->bindValue('bg_color', $command->bg_color, \PDO::PARAM_STR);
		$stmt->bindValue('description', $command->description, \PDO::PARAM_STR);
		$stmt->bindValue('tag_type', $command->tag_type, \PDO::PARAM_STR);
		$stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
		$stmt->executeStatement();

		return (int) $this->db->lastInsertId($schema . '.tags_id_seq');
	}

	public function update(
		TagsDefCommand $command,
		string $schema
	):int
	{
		if (!isset($command->id))
		{
			throw new Exception('no id set for tag update.');
		}

		$stmt = $this->db->prepare('update ' . $schema . '.tags
			set txt = :txt, bg_color = :bg_color, txt_color = :txt_color, description = :description
			where id = :id
				and tag_type = :tag_type');
		$stmt->bindValue('txt', $command->txt, \PDO::PARAM_STR);
		$stmt->bindValue('txt_color', $command->txt_color, \PDO::PARAM_STR);
		$stmt->bindValue('bg_color', $command->bg_color, \PDO::PARAM_STR);
		$stmt->bindValue('description', $command->description, \PDO::PARAM_STR);
		$stmt->bindValue('id', $command->id, \PDO::PARAM_INT);
		$stmt->bindValue('tag_type', $command->tag_type, \PDO::PARAM_STR);
		return $stmt->executeStatement();
	}

	public function del(int $id, string $tag_type, string $schema):int
	{
		$stmt = $this->db->prepare('delete from ' . $schema . '.tags t
			where t.id = :id
				and t.tag_type = :tag_type
				and not exists (
					select from ' . $schema . '.' . $tag_type . '_tags j
					where t.id = j.tag_id)
				');
		$stmt->bindValue('id', $id, \PDO::PARAM_INT);
		$stmt->bindValue('tag_type', $tag_type, \PDO::PARAM_STR);
		return $stmt->executeStatement();
	}

	public function update_list(
		array $tags_list,
		string $tag_type,
		string $schema
	):int
	{
		$tags_count = count($tags_list);
		if ($tags_count < 2)
		{
			return 0;
		}
		$update_count = 0;
		$stored_tags = [];
		$this->db->beginTransaction();
        $stmt = $this->db->prepare('select id
            from ' . $schema . '.tags
            where tag_type = :tag_type
			order by pos');
        $stmt->bindValue('tag_type', $tag_type, \PDO::PARAM_STR);
        $res = $stmt->executeQuery();
		while($tag_id = $res->fetchOne())
		{
			$stored_tags[] = $tag_id;
		}
		if (count($stored_tags) !== $tags_count)
		{
			throw new Exception('Tags count for update does not match.');
		}
		if ($stored_tags === $tags_list)
		{
			$this->db->commit();
			return 0;
		}
		$stmt = $this->db->prepare('select min(pos), max(pos)
			from ' . $schema . '.tags
			where tag_type = :tag_type');
		$stmt->bindValue('tag_type', $tag_type, \PDO::PARAM_STR);
		$res = $stmt->executeQuery();
		[$min_pos, $max_pos] = $res->fetchNumeric();
		$pos = $tags_count >= $min_pos ? $max_pos + 1 : 1;
		foreach ($tags_list as $key => $tag_id)
		{
			$stmt = $this->db->prepare('update ' . $schema . '.tags
				set pos = :pos
				where tag_type = :tag_type
					and id = :id');
			$stmt->bindValue('pos', $pos, \PDO::PARAM_INT);
			$stmt->bindValue('tag_type', $tag_type, \PDO::PARAM_STR);
			$stmt->bindValue('id', $tag_id, \PDO::PARAM_INT);
			$stmt->executeStatement();
			if ($tag_id !== $stored_tags[$key])
			{
				$update_count++;
			}
			$pos++;
		}
		$this->db->commit();
		return $update_count;
	}

	public function is_unique_txt_except_id(
		string $txt,
		int $id,
		string $tag_type,
		string $schema
	):bool
	{
		$lower_txt = trim(strtolower($txt));

		$stmt = $this->db->prepare('select id
			from ' . $schema . '.tags
			where id <> :id
				and tag_type = :tag_type
				and lower(txt) = :lower_txt');
		$stmt->bindValue('id', $id, \PDO::PARAM_INT);
		$stmt->bindValue('tag_type', $tag_type, \PDO::PARAM_STR);
		$stmt->bindValue('lower_txt', $lower_txt, \PDO::PARAM_STR);
		$res = $stmt->executeQuery();
		return $res->fetchOne() === false;
	}
}
