<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Exception;
use Symfony\Component\Uid\Uuid;

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
      txt, txt_color, bg_color,
      description, pos, is_active
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
    array $new_tag_id_ary,
		int $user_id,
    string|null $comment,
    string|null $route,
		int|null $created_by,
		Schema $schema
	):int
	{
    $bulk_id = Uuid::v4()->toRfc4122();

    $all_users_tags = [];
		$all_active_id_keys = [];
		$all_non_active_id_keys = [];
		$insert_id_keys = [];
		$current_tag_id_keys = [];
		$keep_id_keys = [];
		$count_changes = 0;

		$this->db->beginTransaction();

    $stmt_all = $this->db->prepare('select
      t.id as tag_id,
      t.txt, t.txt_color, t.bg_color,
      t.pos, t.description, t.is_active,
      case
        when ut.user_id is not null then true
        else false
      end as user_has_tag
      from ' . $schema->str() . '.tags t
      left join ' . $schema->str() . '.users_tags ut
        on ut.tag_id = t.id
        and ut.user_id = :user_id
      where t.tag_type = \'users\'
      order by t.pos asc');
    $stmt_all->bindValue('user_id', $user_id, Types::INTEGER);
    $res = $stmt_all->executeQuery();

    while ($row = $res->fetchAssociative())
    {
      $all_users_tags[$row['tag_id']] = $row;

      if ($row['user_has_tag'])
      {
        $current_tag_id_keys[$row['tag_id']] = $row['tag_id'];
      }

      if (!$row['is_active'])
      {
        $all_non_active_id_keys[$row['tag_id']] = true;
        continue;
      }

      $all_active_id_keys[$row['tag_id']] = true;
    }

		$stmt_ins = $this->db->prepare('insert into ' .
			$schema->str() . '.users_tags(tag_id, user_id, created_by)
			values(:tag_id, :user_id, :created_by)');

    $stmt_log = $this->db->prepare('insert into ' .
      $schema->str() . '.users_tags_logs(tag_id, user_id, created_by,
        comment, action, route, bulk_id, meta_data)
      values(:tag_id, :user_id, :created_by,
        :comment, :action, :route, :bulk_id, :meta_data)');

		foreach ($new_tag_id_ary as $tag_id)
		{
			if (!isset($all_users_tags[$tag_id]))
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
			if (isset($current_tag_id_keys[$tag_id]))
			{
				$keep_id_keys[$tag_id] = true;
				continue;
			}
			$insert_id_keys[$tag_id] = true;

			$stmt_ins->bindValue('tag_id', $tag_id, Types::INTEGER);
			$stmt_ins->bindValue('user_id', $user_id, Types::INTEGER);
			$stmt_ins->bindValue('created_by', $created_by, Types::INTEGER);
			$stmt_ins->executeStatement();

      $stmt_log->bindValue('tag_id', $tag_id, Types::INTEGER);
      $stmt_log->bindValue('user_id', $user_id, Types::INTEGER);
      $stmt_log->bindValue('created_by', $created_by, Types::INTEGER);
      $stmt_log->bindValue('comment', $comment, Types::STRING);
      $stmt_log->bindValue('action', 'insert', Types::STRING);
      $stmt_log->bindValue('route', $route, Types::STRING);
      $stmt_log->bindValue('bulk_id', $bulk_id, Types::STRING);
      $stmt_log->bindValue('meta_data', $all_users_tags[$tag_id], Types::JSON);
      $stmt_log->executeStatement();

			$count_changes++;
		}

		$stmt_del = $this->db->prepare('delete from ' .
			$schema->str() . '.users_tags
			where tag_id = :tag_id
			and user_id = :user_id');

		foreach ($current_tag_id_keys as $tag_id)
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

			$stmt_del->bindValue('tag_id', $tag_id, Types::INTEGER);
			$stmt_del->bindValue('user_id', $user_id, Types::INTEGER);
			$stmt_del->executeStatement();

      $stmt_log->bindValue('tag_id', $tag_id, Types::INTEGER);
      $stmt_log->bindValue('user_id', $user_id, Types::INTEGER);
      $stmt_log->bindValue('created_by', $created_by, Types::INTEGER);
      $stmt_log->bindValue('comment', $comment, Types::STRING);
      $stmt_log->bindValue('action', 'delete', Types::STRING);
      $stmt_log->bindValue('route', $route, Types::STRING);
      $stmt_log->bindValue('bulk_id', $bulk_id, Types::GUID);
      $stmt_log->bindValue('meta_data', $all_users_tags[$tag_id], Types::JSON);
      $stmt_log->executeStatement();

			$count_changes++;
		}

		$this->db->commit();

		return $count_changes;
	}

  /**
   * @return array user ids where a tag was added
   */
  public function add_tag_for_users(
    array $user_ids,
    int $tag_id,
    string|null $comment,
    string|null $route,
    int|null $created_by,
    Schema $schema
  ):array
  {
    $bulk_id = Uuid::v4()->toRfc4122();

    $this->db->beginTransaction();

    $stmt_tag = $this->db->prepare('select
      t.id, t.txt, t.txt_color, t.bg_color,
      t.pos, t.description, t.is_active,
      t.tag_type
      from ' . $schema->str() . '.tags t
      where t.id = :tag_id
        and t.tag_type = \'users\'
        and t.is_active');
    $stmt_tag->bindValue('tag_id', $tag_id, Types::INTEGER);
    $res = $stmt_tag->executeQuery();
    $tag_meta =$res->fetchAssociative();
    if ($tag_meta === false)
    {
      throw new \Exception(
       'Trying to store non-existing or inactive tag id error ' . $tag_id
      );
    }

    $sql = 'insert into ' . $schema->str() . '.users_tags (
      user_id, tag_id, created_by)
      select u.id, :tag_id, :created_by
      from ' . $schema->str() . '.users u
      where u.id in (:user_ids)
        and not exists (
          select 1 from ' . $schema->str() . '.users_tags ut
          where ut.user_id = u.id and ut.tag_id = :tag_id
        )
      returning user_id';

    $res = $this->db->executeQuery($sql, [
      'tag_id' => $tag_id,
      'created_by' => $created_by,
      'user_ids' => $user_ids,
    ], [
      'tag_id' => Types::INTEGER,
      'created_by' => Types::INTEGER,
      'user_ids' => ArrayParameterType::INTEGER,
    ]);

    $user_ids_add_tag = $res->fetchFirstColumn();

    if (!empty($user_ids_add_tag)) {
      $stmt_log = $this->db->prepare('insert into ' . $schema->str() . '.users_tags_logs(
        tag_id, user_id, created_by,
        comment, action, route, bulk_id, meta_data)
      values(:tag_id, :user_id, :created_by,
        :comment, :action, :route, :bulk_id, :meta_data)');

      foreach ($user_ids_add_tag as $uid) {
        $stmt_log->bindValue('tag_id', $tag_id, Types::INTEGER);
        $stmt_log->bindValue('user_id', $uid, Types::INTEGER);
        $stmt_log->bindValue('created_by', $created_by, Types::INTEGER);
        $stmt_log->bindValue('comment', $comment, Types::STRING);
        $stmt_log->bindValue('action', 'insert', Types::STRING);
        $stmt_log->bindValue('route', $route, Types::STRING);
        $stmt_log->bindValue('bulk_id', $bulk_id, Types::GUID);
        $stmt_log->bindValue('meta_data', $tag_meta, Types::JSON);
        $stmt_log->executeStatement();
      }
    }

    $this->db->commit();

    return $user_ids_add_tag;
  }

  /**
   * @return array user ids where a tag was deleted
   */
  public function del_tag_for_users(
    array $user_ids,
    int $tag_id,
    string|null $comment,
    string|null $route,
    int|null $created_by,
    Schema $schema
  ):array
  {
    $bulk_id = Uuid::v4()->toRfc4122();

    $this->db->beginTransaction();

    $stmt_tag = $this->db->prepare('select
      t.id, t.txt, t.txt_color, t.bg_color,
      t.pos, t.description, t.is_active,
      t.tag_type
      from ' . $schema->str() . '.tags t
      where t.id = :tag_id
        and t.tag_type = \'users\'
        and t.is_active');
    $stmt_tag->bindValue('tag_id', $tag_id, Types::INTEGER);
    $res = $stmt_tag->executeQuery();
    $tag_meta =$res->fetchAssociative();

    if ($tag_meta === false)
    {
      throw new Exception(
        'Trying to delete non-existing or inactive tag id error ' . $tag_id
      );
    }

    $sql = 'delete from ' . $schema->str() . '.users_tags
      where user_id in (:user_ids)
        and tag_id = :tag_id
      returning user_id';

    $res = $this->db->executeQuery($sql, [
      'tag_id' => $tag_id,
      'user_ids' => $user_ids,
    ], [
      'tag_id' => Types::INTEGER,
      'user_ids' => ArrayParameterType::INTEGER,
    ]);

    $user_ids_del_tag = $res->fetchFirstColumn();

    if (!empty($user_ids_del_tag))
    {
      $stmt_log = $this->db->prepare('insert into ' . $schema->str() . '.users_tags_logs(
        tag_id, user_id, created_by,
        comment, action, route, bulk_id, meta_data)
      values(:tag_id, :user_id, :created_by,
        :comment, :action, :route, :bulk_id, :meta_data)');

      foreach ($user_ids_del_tag as $uid)
      {
        $stmt_log->bindValue('tag_id', $tag_id, Types::INTEGER);
        $stmt_log->bindValue('user_id', $uid, Types::INTEGER);
        $stmt_log->bindValue('created_by', $created_by, Types::INTEGER);
        $stmt_log->bindValue('comment', $comment, Types::STRING);
        $stmt_log->bindValue('action', 'insert', Types::STRING);
        $stmt_log->bindValue('route', $route, Types::STRING);
        $stmt_log->bindValue('bulk_id', $bulk_id, Types::GUID);
        $stmt_log->bindValue('meta_data', $tag_meta, Types::JSON);
        $stmt_log->executeStatement();
      }
    }

    $this->db->commit();

    return $user_ids_del_tag;
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

  public function get_all_active_for_users(
    array $user_ids,
    Schema $schema,
  ):array
  {
    $tags_ary = [];

    $res = $this->db->executeQuery('select ut.user_id,
      t.txt, t.txt_color, t.bg_color,
      t.pos, t.description, t.id
      from ' . $schema->str() . '.users_tags ut
      inner join ' . $schema->str() . '.tags t
        on ut.tag_id = t.id
      where t.is_active
        and t.tag_type = \'users\'
        and ut.user_id in (:user_ids)
      order by ut.user_id asc, t.pos asc', [
        'user_ids' => $user_ids,
      ], [
        'user_ids'=> ArrayParameterType::INTEGER,
      ]);

    while (($row = $res->fetchAssociative()) !== false)
    {
      if (!isset($tags_ary[$row['user_id']]))
      {
        $tags_ary[$row['user_id']] = [];
      }
      $tags_ary[$row['user_id']][] = $row;
    }

    return $tags_ary;
  }
}
