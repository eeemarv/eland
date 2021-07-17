<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ForumRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function get_next_topic_id(
		int $ref_topic_id,
		array $visible_ary,
		string $schema
	):int
	{
        $stmt_next = $this->db->executeQuery('select t1.id
			from ' . $schema . '.forum_topics t1,
				' . $schema . '.forum_topics t2
			where t1.last_edit_at < t2.last_edit_at
				and t2.id = ?
				and t1.access in (?)
            order by t1.last_edit_at desc
            limit 1', [
                $ref_topic_id,
                $visible_ary
            ], [
                \PDO::PARAM_INT,
                Db::PARAM_STR_ARRAY,
            ]);

        return $stmt_next->fetchColumn() ?: 0;
	}

	public function get_prev_topic_id(
		int $ref_topic_id,
		array $visible_ary,
		string $schema
	):int
	{
        $stmt_prev = $this->db->executeQuery('select t1.id
			from ' . $schema . '.forum_topics t1,
				' . $schema . '.forum_topics t2
			where t1.last_edit_at > t2.last_edit_at
				and t2.id = ?
				and t1.access in (?)
            order by t1.last_edit_at asc
            limit 1', [
                $ref_topic_id,
                $visible_ary
            ], [
                \PDO::PARAM_INT,
                Db::PARAM_STR_ARRAY,
            ]);

		return $stmt_prev->fetchColumn() ?: 0;
	}

	public function get_topic(int $topic_id, string $schema):array
	{
        $forum_topic = $this->db->fetchAssoc('select *
            from ' . $schema . '.forum_topics
            where id = ?', [$topic_id]);

        if (!isset($forum_topic) || !$forum_topic)
        {
            throw new NotFoundHttpException('Forum topic ' . $topic_id . ' not found.');
		}

		return $forum_topic;
	}

	public function get_topics_with_reply_count(array $visible_ary, string $schema):array
	{
        // to do: order by last post edit desc
        $stmt = $this->db->executeQuery('select t.*, count(p.*) - 1 as reply_count
            from ' . $schema . '.forum_topics t
            inner join ' . $schema . '.forum_posts p on p.topic_id = t.id
            where t.access in (?)
            group by t.id
            order by t.last_edit_at desc',
            [$visible_ary],
            [Db::PARAM_STR_ARRAY]);

        return $stmt->fetchAll();
	}

	public function get_topic_posts(int $topic_id, string $schema):array
	{
        return $this->db->fetchAll('select *
            from ' . $schema . '.forum_posts
            where topic_id = ?
            order by created_at asc', [$topic_id]);
	}

	public function get_post(int $post_id, string $schema):array
	{
        $post = $this->db->fetchAssoc('select *
            from ' . $schema . '.forum_posts
            where id = ?', [$post_id]);

        if (!isset($post) || !$post)
        {
            throw new NotFoundHttpException('Forum post not found.');
		}

		return $post;
	}

	public function get_first_post_id(int $topic_id, string $schema):int
	{
        return $this->db->fetchColumn('select id
            from ' . $schema . '.forum_posts
            where topic_id = ?
            order by created_at asc
            limit 1', [$topic_id]);
	}

	public function get_first_post(int $topic_id, string $schema):array
	{
        return $this->db->fetchAssoc('select *
            from ' . $schema . '.forum_posts
            where topic_id = ?
            order by created_at asc
            limit 1', [$topic_id]);
	}

	public function get_post_count(int $topic_id, string $schema):int
	{
        return $this->db->fetchColumn('select count(*)
            from ' . $schema . '.forum_posts
            where topic_id = ?', [$topic_id]);
	}

	public function del_post(int $post_id, string $schema):bool
	{
		return $this->db->delete($schema . '.forum_posts',
			['id' => $post_id]) ? true : false;
	}

	public function del_topic(int $topic_id, string $schema):bool
	{
		$this->db->delete($schema . '.forum_posts',
			['topic_id' => $topic_id]);
		return $this->db->delete($schema . '.forum_topics',
			['id' => $topic_id]) ? true : false;
	}

	public function insert_topic(
		string $subject,
		string $content,
		string $access,
		int $user_id,
		string $schema
	):int
	{
		$forum_topic_insert = [
			'subject'   => $subject,
			'access'    => $access,
			'user_id'   => $user_id,
		];

		$this->db->insert($schema . '.forum_topics', $forum_topic_insert);

		$id = (int) $this->db->lastInsertId($schema . '.forum_topics_id_seq');

		$forum_post_insert = [
			'content'   => $content,
			'topic_id'  => $id,
			'user_id'   => $user_id,
		];

		$this->db->insert($schema . '.forum_posts', $forum_post_insert);

		return $id;
	}

	public function insert_post(
		string $content,
		int $user_id,
		int $topic_id,
		string $schema
	):bool
	{
		return $this->db->insert($schema . '.forum_posts', [
			'content'		=> $content,
			'user_id'		=> $user_id,
			'topic_id'		=> $topic_id,
		]) ? true : false;
	}

	public function update_post(
		string $content,
		int $post_id,
		string $schema
	):bool
	{
		return $this->db->update($schema . '.forum_posts', [
			'content'		=> $content,
		], ['id' => $post_id]) ? true : false;
	}

	public function update_topic(
		string $subject,
		string $access,
		int $topic_id,
		string $schema
	):bool
	{
		return $this->db->update($schema . '.forum_topics', [
			'subject'       => $subject,
			'access'        => $access,
		], ['id' => $topic_id]) ? true : false;
	}
}
