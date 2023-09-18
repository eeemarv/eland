<?php declare(strict_types=1);

namespace App\Repository;

use App\Command\Tags\TagsDefCommand;
use App\Command\Tags\TagsUsersCommand;
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

	public function get_all(
		string $tag_type,
		string $schema,
		bool $active_only
	):array
	{
		$non_active_included = !$active_only;
		$tags = [];

        $stmt = $this->db->prepare('select id,
			txt, txt_color, bg_color, pos, is_active
            from ' . $schema . '.tags
            where tag_type = :tag_type
				and (\'t\'::bool = :non_active_included
				or is_active)
			order by pos asc');

        $stmt->bindValue('tag_type', $tag_type, \PDO::PARAM_STR);
        $stmt->bindValue('non_active_included', $non_active_included, \PDO::PARAM_BOOL);

        $res = $stmt->executeQuery();

        while ($row = $res->fetchAssociative())
        {
            $tags[] = $row;
        }

		return $tags;
	}

	public function get_txt_ary(
		string $tag_type,
		string $schema,
		bool $active_only
	):array
	{
		$non_active_included = !$active_only;
		$tags = [];

        $stmt = $this->db->prepare('select txt
            from ' . $schema . '.tags
            where tag_type = :tag_type
				and (\'t\'::bool = :non_active_included
				or is_active)
			order by pos');

        $stmt->bindValue('tag_type', $tag_type, \PDO::PARAM_STR);
        $stmt->bindValue('non_active_included', $non_active_included, \PDO::PARAM_BOOL);

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

	public function update_for_user(
		TagsUsersCommand $command,
		int $user_id,
		int $created_by,
		string $schema
	):int
	{
		$this->db->beginTransaction();
		$count_changes = 0;
		$all_users_tags = $this->get_all(tag_type:'users', schema:$schema, active_only:false);

		$all_id_keys = [];
		$all_active_id_keys = [];
		$all_non_active_id_keys = [];
		$insert_id_keys = [];
		$current_id_keys = [];
		$keep_id_keys = [];

		foreach ($all_users_tags as $tag)
		{
			$all_id_keys[$tag['id']] = true;

			if ($tag['is_active'])
			{
				$all_active_id_keys[$tag['id']] = true;
				continue;
			}

			$all_non_active_id_keys[$tag['id']] = true;
		}

		$tag_id_ary_for_user = $this->get_id_ary_for_user($user_id, $schema, active_only:false);

		foreach ($tag_id_ary_for_user as $tag_id)
		{
			$current_id_keys[$tag_id] = true;
		}

		$stmt = $this->db->prepare('insert into ' .
			$schema . '.users_tags(tag_id, user_id, created_by)
			values(:tag_id, :user_id, :created_by)');

		foreach ($command->tags as $tag_id)
		{
			if (!isset($all_id_keys[$tag_id]))
			{
				throw new Exception('Trying to store non-existing tag id error ' . $tag_id);
			}
			if (!isset($all_active_id_keys[$tag_id]))
			{
				throw new Exception('Trying to store non-active tag id error ' . $tag_id);
			}
			if (isset($current_id_keys[$tag_id]))
			{
				$keep_id_keys[$tag_id] = true;
				continue;
			}
			$insert_id_keys[$tag_id] = true;

			$stmt->bindValue('tag_id', $tag_id, \PDO::PARAM_INT);
			$stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
			$stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
			$stmt->executeStatement();
			$count_changes++;
		}

		$stmt = $this->db->prepare('delete from ' .
			$schema . '.users_tags
			where tag_id = :tag_id
			and user_id = :user_id');

		foreach ($tag_id_ary_for_user as $tag_id)
		{
			if (isset($all_non_active_id_keys[$tag_id]))
			{
				continue;
			}
			if (isset($insert_id_keys[$tag_id]))
			{
				continue;
			}
			if (isset($keep_id_keys[$tag_id]))
			{
				continue;
			}

			$stmt->bindValue('tag_id', $tag_id, \PDO::PARAM_INT);
			$stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
			$stmt->executeStatement();
			$count_changes++;
		}

		$this->db->commit();

		return $count_changes;
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

	public function get_id_ary_for_user(
		int $user_id,
		string $schema,
		bool $active_only
	):array
	{
		$non_active_included = !$active_only;
		$tag_ids = [];

        $stmt = $this->db->prepare('select t.id
            from ' . $schema . '.tags t
				inner join ' . $schema . '.users_tags ut
					on ut.tag_id = t.id
				inner join ' . $schema . '.users u
					on u.id = ut.user_id
            where t.tag_type = \'users\'
				and (\'t\'::bool = :non_active_included or t.is_active)
				and u.id = :user_id
			order by t.pos asc');

        $stmt->bindValue('non_active_included', $non_active_included, \PDO::PARAM_BOOL);
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $res = $stmt->executeQuery();

        while ($row = $res->fetchAssociative())
        {
            $tag_ids[] = $row['id'];
        }

		return $tag_ids;
	}

	public function get_all_active_for_message(
		int $message_id,
		string $schema
	):array
	{
		$tags = [];

        $stmt = $this->db->prepare('select t.id
            from ' . $schema . '.tags t
				inner join ' . $schema . '.messages_tags mt
					on mt.tag_id = t.id
				inner join ' . $schema . '.messages m
					on m.id = mt.message_id
            where t.tag_type = \'messages\'
				and t.is_active
				and m.id = :message_id
			order by t.pos asc');
        $stmt->bindValue('message_id', $message_id, \PDO::PARAM_INT);
        $res = $stmt->executeQuery();

        while ($row = $res->fetchAssociative())
        {
            $tags[] = $row['id'];
        }

		return $tags;
	}

	public function get_all_active_for_news(
		int $news_id,
		string $schema
	):array
	{
		$tags = [];

        $stmt = $this->db->prepare('select t.id
            from ' . $schema . '.tags t
				inner join ' . $schema . '.news_tags nt
					on nt.tag_id = t.id
				inner join ' . $schema . '.news n
					on n.id = nt.news_id
            where t.tag_type = \'news\'
				and t.is_active = \'t\'::bool
				and n.id = :news_id
			order by t.pos asc');
        $stmt->bindValue('news_id', $news_id, \PDO::PARAM_INT);
        $res = $stmt->executeQuery();

        while ($row = $res->fetchAssociative())
        {
            $tags[] = $row['id'];
        }

		return $tags;
	}
}
