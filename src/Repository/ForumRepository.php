<?php declare(strict_types=1);

namespace App\Repository;

use App\Command\Forum\ForumPostCommand;
use App\Command\Forum\ForumTopicCommand;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ForumRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function get_topic(int $topic_id, string $schema):array
	{
        $topic = $this->db->fetchAssociative('select *
            from ' . $schema . '.forum_topics
            where id = ?', [$topic_id], [\PDO::PARAM_INT]);

        if ($topic === false)
        {
            throw new NotFoundHttpException('Forum topic ' . $topic_id . ' not found.');
		}

		return $topic;
	}

	public function get_topic_with_prev_next(
		int $topic_id,
		array $visible_ary,
		string $schema
	):array
	{
        $topic = $this->db->fetchAssociative('select s.*
			from (select t.*, count(p.*) - 1 as reply_count,
				lag(t.id) over (order by max(p.last_edit_at) desc) as prev_id,
				lead(t.id) over (order by max(p.last_edit_at) desc) as next_id
				from ' . $schema . '.forum_topics t
				inner join ' . $schema . '.forum_posts p on p.topic_id = t.id
				where t.access in (?)
				group by t.id) s
			where s.id = ?',
            [$visible_ary, $topic_id],
            [Db::PARAM_STR_ARRAY, \PDO::PARAM_INT]);

		if ($topic === false)
		{
			throw new NotFoundHttpException('Forum topic ' . $topic_id . ' not found.');
		}

		return $topic;
	}

	public function get_topics_with_reply_count(array $visible_ary, string $schema):array
	{
        $res = $this->db->executeQuery('select t.*, count(p.*) - 1 as reply_count
            from ' . $schema . '.forum_topics t
            inner join ' . $schema . '.forum_posts p on p.topic_id = t.id
            where t.access in (?)
            group by t.id
            order by max(p.last_edit_at) desc',
            [$visible_ary],
            [Db::PARAM_STR_ARRAY]);

        return $res->fetchAllAssociative() ?: [];
	}

	public function get_topic_posts(int $topic_id, string $schema):array
	{
        return $this->db->fetchAllAssociative('select *
            from ' . $schema . '.forum_posts
            where topic_id = ?
            order by created_at asc',
			[$topic_id],
			[\PDO::PARAM_INT]) ?: [];
	}

	public function get_post(int $post_id, string $schema):array
	{
        $post = $this->db->fetchAssociative('select *
            from ' . $schema . '.forum_posts
            where id = ?', [$post_id], [\PDO::PARAM_INT]);

        if (!isset($post) || !$post)
        {
            throw new NotFoundHttpException('Forum post not found.');
		}

		return $post;
	}

	public function get_first_post_id(int $topic_id, string $schema):int
	{
        return $this->db->fetchOne('select id
            from ' . $schema . '.forum_posts
            where topic_id = ?
            order by created_at asc
            limit 1',
			[$topic_id],
			[\PDO::PARAM_INT]);
	}

	public function get_first_post(int $topic_id, string $schema):array
	{
        return $this->db->fetchAssociative('select *
            from ' . $schema . '.forum_posts
            where topic_id = ?
            order by created_at asc
            limit 1',
			[$topic_id],
			[\PDO::PARAM_INT]);
	}

	public function get_post_count(int $topic_id, string $schema):int
	{
        return $this->db->fetchOne('select count(*)
            from ' . $schema . '.forum_posts
            where topic_id = ?',
			[$topic_id],
			[\PDO::PARAM_INT]);
	}

	public function del_post(int $post_id, string $schema):bool
	{
		return $this->db->delete($schema . '.forum_posts',
			['id' => $post_id]) ? true : false;
	}

	public function del_topic(int $topic_id, string $schema):bool
	{
		$this->db->beginTransaction();
		$this->db->delete($schema . '.forum_posts',
			['topic_id' => $topic_id]);
		$this->db->delete($schema . '.forum_topics',
			['id' => $topic_id]);
		return $this->db->commit();
	}

	public function insert_topic(
		ForumTopicCommand $command,
		int $user_id,
		string $schema
	):int
	{
		$forum_topic_insert = [
			'subject'   => $command->subject,
			'access'    => $command->access,
			'user_id'   => $user_id,
		];

		$types = [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_INT];

		$this->db->insert($schema . '.forum_topics', $forum_topic_insert, $types);

		$id = (int) $this->db->lastInsertId($schema . '.forum_topics_id_seq');

		$forum_post_insert = [
			'content'   => $command->content,
			'topic_id'  => $id,
			'user_id'   => $user_id,
		];

		$types = [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT];

		$this->db->insert($schema . '.forum_posts', $forum_post_insert, $types);

		return $id;
	}

	public function insert_post(
		ForumPostCommand $command,
		int $user_id,
		int $topic_id,
		string $schema
	):int
	{
		$this->db->insert($schema . '.forum_posts', [
			'content'		=> $command->content,
			'user_id'		=> $user_id,
			'topic_id'		=> $topic_id,
		], [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]);
		return (int) $this->db->lastInsertId($schema . '.forum_posts_id_seq');
	}

	public function update_post(
		int $post_id,
		ForumPostCommand $command,
		string $schema
	):bool
	{
		return $this->db->update($schema . '.forum_posts', [
			'content'	=> $command->content,
		], ['id' => $post_id]) ? true : false;
	}

	public function update_topic(
		int $topic_id,
		ForumTopicCommand $command,
		string $schema
	):bool
	{
		$post_id = $this->get_first_post_id($topic_id, $schema);

		$this->db->beginTransaction();

		$this->db->update($schema . '.forum_topics', [
			'subject'       => $command->subject,
			'access'        => $command->access,
		], ['id' => $topic_id]);
		$this->db->update($schema . '.forum_posts', [
			'content'	=> $command->content,
		], ['id' => $post_id]);

		return $this->db->commit();
	}
}
