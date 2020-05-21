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

	public function get_post(int $id, string $schema):array
	{
        $post = $this->db->fetchAssoc('select *
            from ' . $schema . '.forum_posts
            where id = ?', [$id]);

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

	public function del_post(int $id, string $schema):bool
	{
		return $this->db->delete($schema . '.forum_posts',
			['id' => $id]) ? true : false;
	}

	public function del_topic(int $id, string $schema):bool
	{
		/*
		return $this->db->delete($schema . '.forum_topics',
			['id' => $id]) ? true : false;
		*/
	}



}
