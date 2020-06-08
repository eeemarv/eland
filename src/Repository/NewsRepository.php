<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NewsRepository
{
	protected Db $db;

	public function __construct(
		Db $db
	)
	{
		$this->db = $db;
	}

	public function get(int $id, string $schema):array
	{
        $news = $this->db->fetchAssoc('select *
            from ' . $schema . '.news
            where id = ?', [$id]);

		if (!$news)
		{
			throw new NotFoundHttpException('News with id %d not found');
		}

		return $news;
	}

	public function del(int $id, string $schema):bool
	{
		return $this->db->delete($schema . '.news',
			['id' => $id]) ? true : false;
	}

	public function get_all(
		bool $event_at_asc_en,
		array $visible_ary,
		string $schema
	):array
	{
		$order = $event_at_asc_en ? 'asc' : 'desc';

		$stmt = $this->db->executeQuery('select *
			from ' . $schema . '.news
			where access in (?)
			order by event_at ' . $order . ' ,created_at desc',
			[$visible_ary],
			[Db::PARAM_STR_ARRAY]);

		return $stmt->fetchAll();
	}

}
