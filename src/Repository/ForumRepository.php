<?php declare(strict_types=1);

namespace App\Repository;

use App\Command\Forum\ForumPostCommand;
use App\Command\Forum\ForumTopicCommand;
use App\DTO\Schema;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
  ):array
	{
    $topic = $this->db->fetchAssociative('select *
      from ' . $schema->str() . '.forum_topics
      where id = :topic_id', [
        'topic_id' => $topic_id
      ], [
        'topic_id' => Types::INTEGER,
      ]);

    if ($topic === false)
    {
      throw new NotFoundHttpException('Forum topic ' . $topic_id . ' not found.');
		}

		return $topic;
	}

	public function get_topic_with_prev_next(
		int $topic_id,
		array $visible_ary,
		Schema $schema,
	):array
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

		if ($topic === false)
		{
			throw new NotFoundHttpException('Forum topic ' . $topic_id . ' not found.');
		}

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
  ):array
	{
    $post = $this->db->fetchAssociative('select *
      from ' . $schema->str() . '.forum_posts
      where id = :post_id', [
        'post_id'   => $post_id,
      ], [
        'post_id'   => Types::INTEGER,
      ]);

    if (!isset($post) || !$post)
    {
      throw new NotFoundHttpException('Forum post not found.');
		}

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
		$this->db->delete($schema . '.forum_topics',[
      'id' => $topic_id,
    ], [
      'id'  => Types::INTEGER,
    ]);
		return $this->db->commit();
	}

	public function insert_topic(
		ForumTopicCommand $command,
		int $user_id,
		Schema $schema
	):int
	{
		$topic_insert = [
			'subject'   => $command->subject,
			'access'    => $command->access,
			'user_id'   => $user_id,
		];

		$topic_types = [
      'subject' => Types::STRING,
      'access'  => Types::STRING,
      'user_id' => Types::INTEGER,
    ];

		$this->db->insert($schema->str() . '.forum_topics', $topic_insert, $topic_types);

		$id = (int) $this->db->lastInsertId($schema->str() . '.forum_topics_id_seq');

		$post_insert = [
			'content'   => $command->content,
			'topic_id'  => $id,
			'user_id'   => $user_id,
		];

		$post_types = [
      'content'   => Types::STRING,
      'topic_id'  => Types::INTEGER,
      'user_id'   => Types::INTEGER,
    ];

		$this->db->insert($schema->str() . '.forum_posts', $post_insert, $post_types);

		return $id;
	}

	public function insert_post(
		ForumPostCommand $command,
		int $user_id,
		int $topic_id,
		Schema $schema
	):int
	{
		$this->db->insert($schema->str() . '.forum_posts', [
			'content'		=> $command->content,
			'user_id'		=> $user_id,
			'topic_id'	=> $topic_id,
		], [
      'content'   => Types::STRING,
      'user_id'   => Types::INTEGER,
      'topic_id'  => Types::INTEGER,
    ]);
		return (int) $this->db->lastInsertId($schema->str() . '.forum_posts_id_seq');
	}

	public function update_post(
		int $post_id,
		ForumPostCommand $command,
		Schema $schema
	):bool
	{
		return $this->db->update($schema->str() . '.forum_posts', [
			'content'	=> $command->content,
		], [
      'id' => $post_id,
    ], [
      'content' => Types::STRING,
      'id'      => Types::INTEGER,
    ]) ? true : false;
	}

	public function update_topic(
		int $topic_id,
		ForumTopicCommand $command,
		Schema $schema
	):bool
	{
		$post_id = $this->get_first_post_id(
      topic_id: $topic_id,
      schema: $schema,
    );

		$this->db->beginTransaction();

		$this->db->update($schema->str() . '.forum_topics', [
			'subject'       => $command->subject,
			'access'        => $command->access,
		], [
      'id' => $topic_id,
    ], [
      'subject' => Types::STRING,
      'access'  => Types::STRING,
      'id'      => Types::INTEGER,
    ]);
		$this->db->update($schema->str() . '.forum_posts', [
			'content'	=> $command->content,
		], [
      'id' => $post_id,
    ], [
      'content' => Types::STRING,
      'id'  => Types::INTEGER,
    ]);

		return $this->db->commit();
	}
}
