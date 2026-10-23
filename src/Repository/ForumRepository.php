<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class ForumRepository
{
	public function __construct(
		private readonly Db $db
	)
	{
	}

	public function get_topic(
    int $topic_id,
    Schema $schema,
  ):array|false
	{
    $topic = $this->db->fetchAssociative('select *
      from ' . $schema->str() . '.forum_topics
      where id = :topic_id', [
        'topic_id' => $topic_id
      ], [
        'topic_id' => Types::INTEGER,
      ]);

		return $topic;
	}

	public function get_topic_with_prev_next(
		int $topic_id,
		array $visible_ary,
		Schema $schema,
	):array|false
	{
    $topic = $this->db->fetchAssociative('select s.*
			from (select t.*, count(p.*) - 1 as reply_count,
				lag(t.id) over (order by max(p.last_edit_at) desc) as prev_id,
				lead(t.id) over (order by max(p.last_edit_at) desc) as next_id
				from ' . $schema->str() . '.forum_topics t
				inner join ' . $schema->str() . '.forum_posts p on p.topic_id = t.id
				where t.access in (:visible_ary)
				group by t.id) s
			where s.id = :topic_id', [
        'visible_ary'   => $visible_ary,
        'topic_id'      => $topic_id,
      ], [
        'visible_ary'   => ArrayParameterType::STRING,
        'topic_id'      => Types::INTEGER,
      ]);

		return $topic;
	}

	public function get_topics_with_reply_count(
    array $visible_ary,
    Schema $schema,
  ):array
	{
    $res = $this->db->executeQuery('select t.*, count(p.*) - 1 as reply_count
      from ' . $schema->str() . '.forum_topics t
      inner join ' . $schema->str() . '.forum_posts p on p.topic_id = t.id
      where t.access in (:visible_ary)
      group by t.id
      order by max(p.last_edit_at) desc', [
        'visible_ary'   => $visible_ary,
      ], [
        'visible_ary'   => ArrayParameterType::STRING,
      ]);

    return $res->fetchAllAssociative();
	}

	public function get_topic_posts(
    int $topic_id,
    Schema $schema,
  ):array
	{
    return $this->db->fetchAllAssociative('select *
      from ' . $schema->str() . '.forum_posts
        where topic_id = :topic_id
        order by created_at asc', [
      'topic_id'  => $topic_id,
    ], [
      'topic_id'  => Types::INTEGER,
    ]);
	}

	public function get_post(
    int $post_id,
    Schema $schema,
  ):array|false
	{
    $post = $this->db->fetchAssociative('select *
      from ' . $schema->str() . '.forum_posts
      where id = :post_id', [
        'post_id'   => $post_id,
      ], [
        'post_id'   => Types::INTEGER,
      ]);

		return $post;
	}

	public function get_first_post_id(
    int $topic_id,
    Schema $schema,
  ):int|false
	{
    return $this->db->fetchOne('select id
        from ' . $schema->str() . '.forum_posts
        where topic_id = :topic_id
        order by created_at asc
        limit 1',[
      'topic_id'  => $topic_id,
    ], [
      'topic_id'  => Types::INTEGER,
    ]);
	}

	public function get_first_post(
    int $topic_id,
    Schema $schema,
  ):array
	{
    return $this->db->fetchAssociative('select *
      from ' . $schema->str() . '.forum_posts
      where topic_id = :topic_id
      order by created_at asc
      limit 1', [
        'topic_id'  => $topic_id,
      ], [
        'topic_id'  => Types::INTEGER,
      ]);
	}

	public function get_post_count(
    int $topic_id,
    Schema $schema,
  ):int
	{
    return $this->db->fetchOne('select count(*)
      from ' . $schema->str() . '.forum_posts
      where topic_id = :topic_id', [
        'topic_id'  => $topic_id,
      ], [
        'topic_id'  => Types::INTEGER,
      ]);
	}

	public function del_post(
    int $post_id,
    Schema $schema,
  ):bool
	{
		return $this->db->delete($schema->str() . '.forum_posts', [
      'id' => $post_id,
    ], [
      'id'  => Types::INTEGER,
    ]) ? true : false;
	}

	public function del_topic(
    int $topic_id,
    Schema $schema,
  ):bool
	{
		$this->db->beginTransaction();
		$this->db->delete($schema->str() . '.forum_posts',[
      'topic_id'  => $topic_id,
    ], [
      'topic_id'  => Types::INTEGER,
    ]);
		$this->db->delete($schema->str() . '.forum_topics',[
      'id' => $topic_id,
    ], [
      'id'  => Types::INTEGER,
    ]);
    $this->db->commit();
		return true;
	}

	public function insert_topic(
    string $subject,
    string $content,
    string $access,
		int $user_id,
		Schema $schema
	):int
	{
    /* use returning id
		$this->db->insert($schema->str() . '.forum_topics', [
			'subject'   => $subject,
			'access'    => $access,
			'user_id'   => $user_id,
		], [
      'subject' => Types::STRING,
      'access'  => Types::STRING,
      'user_id' => Types::INTEGER,
    ]);
  */

    $stmt_1 = $this->db->prepare('insert into ' . $schema->str() . '.forum_topics
      (subject, access, user_id)
      values
      (:subject, :access, :user_id)
      returning id');
    $stmt_1->bindValue('subject', $subject, Types::STRING);
    $stmt_1->bindValue('access', $access, Types::STRING);
    $stmt_1->bindValue('user_id', $user_id, Types::INTEGER);
    $res = $stmt_1->executeQuery();
    $topic_id = (int) $res->fetchOne();

    $stmt_2 = $this->db->prepare('insert into ' . $schema->str() . '.forum_posts
      (content, topic_id, user_id)
      values
      (:content, :topic_id, :user_id)');
    $stmt_2->bindValue('content', $content, Types::STRING);
    $stmt_2->bindValue('topic_id', $topic_id, Types::INTEGER);
    $stmt_2->bindValue('user_id', $user_id, Types::INTEGER);
    $stmt_2->executeStatement();

		return $topic_id;
	}

	public function insert_post(
    string $content,
		int $user_id,
		int $topic_id,
		Schema $schema
	):int
	{
    $stmt = $this->db->prepare('insert into ' . $schema->str() . '.forum_posts
      (content, topic_id, user_id)
      values
      (:content, :topic_id, :user_id)
      returning id');
    $stmt->bindValue('content', $content, Types::STRING);
    $stmt->bindValue('topic_id', $topic_id, Types::INTEGER);
    $stmt->bindValue('user_id', $user_id, Types::INTEGER);
    $res = $stmt->executeQuery();
    $post_id = (int) $res->fetchOne();

		return $post_id;
	}

	public function update_post(
		int $post_id,
		string $content,
		Schema $schema
	):int
	{
		return (int) $this->db->update($schema->str() . '.forum_posts', [
			'content'	=> $content,
		], [
      'id' => $post_id,
    ], [
      'content' => Types::STRING,
      'id'      => Types::INTEGER,
    ]);
	}

	public function update_topic(
		int $topic_id,
    string $subject,
    string $content,
    string $access,
		Schema $schema
	):bool
	{
		$post_id = $this->get_first_post_id(
      topic_id: $topic_id,
      schema: $schema,
    );

		$this->db->beginTransaction();

		$this->db->update($schema->str() . '.forum_topics', [
			'subject'       => $subject,
			'access'        => $access,
		], [
      'id' => $topic_id,
    ], [
      'subject' => Types::STRING,
      'access'  => Types::STRING,
      'id'      => Types::INTEGER,
    ]);
		$this->db->update($schema->str() . '.forum_posts', [
			'content'	=> $content,
		], [
      'id' => $post_id,
    ], [
      'content' => Types::STRING,
      'id'  => Types::INTEGER,
    ]);
    $this->db->commit();
		return true;
	}
}
