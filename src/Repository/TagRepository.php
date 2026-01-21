<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Exception;

class TagRepository
{
	public function __construct(
		private readonly Db $db
	)
	{
	}

	public function insert(
    string $tag_type,
    string $txt,
    string $txt_color,
    string $bg_color,
    string|null $description,
    int|null $created_by,
		Schema $schema
	):int
	{
		$stmt = $this->db->prepare('insert into ' . $schema->str() . '.tags
			(txt, txt_color, bg_color, description, tag_type, created_by, pos)
			values(:txt, :txt_color, :bg_color, :description, :tag_type, :created_by, (
				select coalesce(max(pos), 0) + 1 from ' . $schema->str() . '.tags
				where tag_type = :tag_type
			))');

		$stmt->bindValue('txt', $txt, Types::STRING);
		$stmt->bindValue('txt_color', $txt_color, Types::STRING);
		$stmt->bindValue('bg_color', $bg_color, Types::STRING);
		$stmt->bindValue('description', $description, Types::STRING);
		$stmt->bindValue('tag_type', $tag_type, Types::STRING);
		$stmt->bindValue('created_by', $created_by, Types::INTEGER);
		$stmt->executeStatement();

		return (int) $this->db->lastInsertId($schema->str() . '.tags_id_seq');
	}

	public function update(
    int $id,
    string $tag_type,
    string $txt,
    string $txt_color,
    string $bg_color,
    string|null $description,
		Schema $schema
	):int
	{
		$stmt = $this->db->prepare('update ' . $schema->str() . '.tags
			set txt = :txt, bg_color = :bg_color, txt_color = :txt_color, description = :description
			where id = :id
				and tag_type = :tag_type');

		$stmt->bindValue('txt', $txt, Types::STRING);
		$stmt->bindValue('txt_color', $txt_color, Types::STRING);
		$stmt->bindValue('bg_color', $bg_color, Types::STRING);
		$stmt->bindValue('description', $description, Types::STRING);
		$stmt->bindValue('id', $id, Types::INTEGER);
		$stmt->bindValue('tag_type', $tag_type, Types::STRING);

		return $stmt->executeStatement();
	}

	public function del(
    int $id,
    string $tag_type,
    Schema $schema
  ):int
	{
		$stmt = $this->db->prepare('delete from ' . $schema->str() . '.tags t
			where t.id = :id
				and t.tag_type = :tag_type
				and not exists (
					select from ' . $schema->str() . '.' . $tag_type . '_tags j
					where t.id = j.tag_id)
			');
		$stmt->bindValue('id', $id, Types::INTEGER);
		$stmt->bindValue('tag_type', $tag_type, Types::STRING);

		return $stmt->executeStatement();
	}

	public function get(
		int $id,
		string $tag_type,
		Schema $schema,
	):array|false
	{
		$stmt = $this->db->prepare('select *
			from ' . $schema->str() . '.tags
			where id = :id
				and tag_type = :tag_type');

		$stmt->bindValue('id', $id, Types::INTEGER);
		$stmt->bindValue('tag_type', $tag_type, Types::STRING);
		$res = $stmt->executeQuery();

		$tag =  $res->fetchAssociative();

		return $tag;
	}

	public function get_with_count(
		int $id,
		string $tag_type,
		Schema $schema
	):array|false
	{
		$stmt = $this->db->prepare('select t.*, count(j.*)
			from ' . $schema->str() . '.tags t
				left join ' . $schema->str() . '.' . $tag_type . '_tags j
				on t.id = j.tag_id
			where t.id = :id
				and t.tag_type = :tag_type
			group by t.id');

		$stmt->bindValue('id', $id, Types::INTEGER);
		$stmt->bindValue('tag_type', $tag_type, Types::STRING);
		$res = $stmt->executeQuery();
		$tag = $res->fetchAssociative();

		return $tag;
	}

	public function get_all_with_count(
		string $tag_type,
		Schema $schema
	):array
	{
		$stmt = $this->db->prepare('select t.*, count(j.*)
			from ' . $schema->str() . '.tags t
			left join ' . $schema->str() . '.' . $tag_type . '_tags j
			on t.id = j.tag_id
			where t.tag_type = :tag_type
			group by t.id
			order by t.pos asc;');
		$stmt->bindValue('tag_type', $tag_type, Types::STRING);
		$res = $stmt->executeQuery();
		return $res->fetchAllAssociative();
	}

	public function get_all_for_render(
		string $tag_type,
		Schema $schema
	):array
	{
		$tags = [];

    $stmt = $this->db->prepare('select id, txt, txt_color, bg_color, pos
        from ' . $schema->str() . '.tags
        where tag_type = :tag_type
      order by pos asc');

    $stmt->bindValue('tag_type', $tag_type, Types::STRING);

    $res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
    {
      $tags[] = $row;
    }

		return $tags;
	}

	public function get_flat_ary(
		string $tag_type,
		Schema $schema
	):array
	{
		$tags = [];

    $stmt = $this->db->prepare('select txt
      from ' . $schema->str() . '.tags
      where tag_type = :tag_type
      order by pos');
    $stmt->bindValue('tag_type', $tag_type, Types::STRING);
    $res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
    {
      $tags[] = $row['txt'];
    }

		return $tags;
	}

	public function update_list(
		array $tags_list,
		string $tag_type,
		Schema $schema
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
      from ' . $schema->str() . '.tags
      where tag_type = :tag_type
			order by pos');
    $stmt->bindValue('tag_type', $tag_type, Types::STRING);
    $res = $stmt->executeQuery();

		while($tag_id = $res->fetchOne())
		{
			$stored_tags[] = $tag_id;
		}

		if (count($stored_tags) !== $tags_count)
		{
			throw new Exception(
        'Tags count for update does not match.'
      );
		}

		if ($stored_tags === $tags_list)
		{
			$this->db->commit();
			return 0;
		}

		$stmt = $this->db->prepare('select min(pos), max(pos)
			from ' . $schema->str() . '.tags
			where tag_type = :tag_type');
		$stmt->bindValue('tag_type', $tag_type, Types::STRING);
		$res = $stmt->executeQuery();
		[$min_pos, $max_pos] = $res->fetchNumeric();
		$pos = $tags_count >= $min_pos ? $max_pos + 1 : 1;
		foreach ($tags_list as $key => $tag_id)
		{
			$stmt = $this->db->prepare('update ' . $schema->str() . '.tags
				set pos = :pos
				where tag_type = :tag_type
					and id = :id');
			$stmt->bindValue('pos', $pos, Types::INTEGER);
			$stmt->bindValue('tag_type', $tag_type, Types::STRING);
			$stmt->bindValue('id', $tag_id, Types::INTEGER);
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
		Schema $schema,
	):bool
	{
		$lower_txt = trim(strtolower($txt));

		$stmt = $this->db->prepare('select id
			from ' . $schema->str() . '.tags
			where id <> :id
				and tag_type = :tag_type
				and lower(txt) = :lower_txt');
		$stmt->bindValue('id', $id, Types::INTEGER);
		$stmt->bindValue('tag_type', $tag_type, Types::STRING);
		$stmt->bindValue('lower_txt', $lower_txt, Types::STRING);
		$res = $stmt->executeQuery();
		return $res->fetchOne() === false;
	}

	public function get_all(
		string $tag_type,
		Schema $schema,
		bool $active_only,
	):array
	{
		$non_active_included = !$active_only;
		$tags = [];

    $stmt = $this->db->prepare('select id,
      txt, txt_color, bg_color, pos, is_active
      from ' . $schema->str() . '.tags
      where tag_type = :tag_type
      and (:non_active_included or is_active)
      order by pos asc');

    $stmt->bindValue('tag_type', $tag_type, Types::STRING);
    $stmt->bindValue('non_active_included', $non_active_included, Types::BOOLEAN);

    $res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
    {
      $tags[] = $row;
    }

		return $tags;
	}

	public function get_txt_ary(
		string $tag_type,
		Schema $schema,
		bool $active_only
	):array
	{
		$non_active_included = !$active_only;
		$tags = [];

    $stmt = $this->db->prepare('select txt
      from ' . $schema->str() . '.tags
      where tag_type = :tag_type
      and (:non_active_included or is_active)
      order by pos');

    $stmt->bindValue('tag_type', $tag_type, Types::STRING);
    $stmt->bindValue('non_active_included', $non_active_included, Types::BOOLEAN);

		$res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
    {
      $tags[] = $row['txt'];
    }

		return $tags;
	}

	public function update_for_user(
		TagsUsersCommand $command,
		int $user_id,
		int $created_by,
		Schema $schema
	):int
	{
		$this->db->beginTransaction();
		$count_changes = 0;
		$all_users_tags = $this->get_all(
      tag_type: 'users',
      schema: $schema,
      active_only:false,
    );

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

		$tag_id_ary_for_user = $this->get_id_ary_for_user(
      user_id: $user_id,
      schema: $schema,
      active_only: false,
    );

		foreach ($tag_id_ary_for_user as $tag_id)
		{
			$current_id_keys[$tag_id] = true;
		}

		$stmt = $this->db->prepare('insert into ' .
			$schema->str() . '.users_tags(tag_id, user_id, created_by)
			values(:tag_id, :user_id, :created_by)');

		foreach ($command->tags as $tag_id)
		{
			if (!isset($all_id_keys[$tag_id]))
			{
				throw new Exception(
          'Trying to store non-existing tag id error ' . $tag_id
        );
			}
			if (!isset($all_active_id_keys[$tag_id]))
			{
				throw new Exception(
          'Trying to store non-active tag id error ' . $tag_id
        );
			}
			if (isset($current_id_keys[$tag_id]))
			{
				$keep_id_keys[$tag_id] = true;
				continue;
			}
			$insert_id_keys[$tag_id] = true;

			$stmt->bindValue('tag_id', $tag_id, Types::INTEGER);
			$stmt->bindValue('user_id', $user_id, Types::INTEGER);
			$stmt->bindValue('created_by', $created_by, Types::INTEGER);
			$stmt->executeStatement();
			$count_changes++;
		}

		$stmt = $this->db->prepare('delete from ' .
			$schema->str() . '.users_tags
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

			$stmt->bindValue('tag_id', $tag_id, Types::INTEGER);
			$stmt->bindValue('user_id', $user_id, Types::INTEGER);
			$stmt->executeStatement();
			$count_changes++;
		}

		$this->db->commit();

		return $count_changes;
	}

	public function get_id_ary_for_user(
		int $user_id,
		Schema $schema,
		bool $active_only
	):array
	{
		$non_active_included = !$active_only;
		$tag_ids = [];

    $stmt = $this->db->prepare('select t.id
      from ' . $schema->str() . '.tags t
      inner join ' . $schema->str() . '.users_tags ut
        on ut.tag_id = t.id
      inner join ' . $schema->str() . '.users u
        on u.id = ut.user_id
      where t.tag_type = \'users\'
				and (:non_active_included or t.is_active)
				and u.id = :user_id
			order by t.pos asc');

    $stmt->bindValue('non_active_included', $non_active_included, Types::BOOLEAN);
    $stmt->bindValue('user_id', $user_id, Types::INTEGER);
    $res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
    {
        $tag_ids[] = $row['id'];
    }

		return $tag_ids;
	}

	public function get_all_active_for_message(
		int $message_id,
		Schema $schema,
	):array
	{
		$tags = [];

    $stmt = $this->db->prepare('select t.id
      from ' . $schema->str() . '.tags t
      inner join ' . $schema->str() . '.messages_tags mt
        on mt.tag_id = t.id
      inner join ' . $schema->str() . '.messages m
        on m.id = mt.message_id
      where t.tag_type = \'messages\'
        and t.is_active
        and m.id = :message_id
      order by t.pos asc');

    $stmt->bindValue('message_id', $message_id, Types::INTEGER);
    $res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
    {
      $tags[] = $row['id'];
    }

		return $tags;
	}

	public function get_all_active_for_news(
		int $news_id,
		Schema $schema,
	):array
	{
		$tags = [];

    $stmt = $this->db->prepare('select t.id
      from ' . $schema->str() . '.tags t
      inner join ' . $schema->str() . '.news_tags nt
        on nt.tag_id = t.id
      inner join ' . $schema->str() . '.news n
        on n.id = nt.news_id
      where t.tag_type = \'news\'
				and t.is_active
				and n.id = :news_id
			order by t.pos asc');
    $stmt->bindValue('news_id', $news_id, Types::INTEGER);
    $res = $stmt->executeQuery();

    while ($row = $res->fetchAssociative())
    {
      $tags[] = $row['id'];
    }

		return $tags;
	}
}
