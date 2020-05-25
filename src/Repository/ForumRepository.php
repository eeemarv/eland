<?php declare(strict_types=1);

namespace App\Repository;

use App\Service\ItemAccessService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ForumRepository
{
	protected Db $db;
	protected ItemAccessService $item_access_service;

	public function __construct(
		Db $db,
		ItemAccessService $item_access_service
	)
	{
		$this->db = $db;
		$this->item_access_service = $item_access_service;
	}

	public function get_visible_topic_for_page(int $id, string $schema):array
	{
        $stmt = $this->db->executeQuery('select *
            from ' . $schema . '.forum_topics
            where id = ?
                and access in (?)', [
                $id,
                $this->item_access_service->get_visible_ary_for_page()
            ], [
                \PDO::PARAM_INT,
                Db::PARAM_STR_ARRAY,
            ]);

        $topic = $stmt->fetch();

        if (!isset($topic) || !$topic)
        {
            throw new NotFoundHttpException('Forum topic not found.');
		}

		return $topic;
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

}
