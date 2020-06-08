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
			order by event_at ' . $order . ',created_at desc',
			[$visible_ary],
			[Db::PARAM_STR_ARRAY]);

		return $stmt->fetchAll();
	}

	public function get_prev_and_next_id(
		int $ref_news_id,
		bool $event_at_asc_en,
		array $visible_ary,
		string $schema
	):array
	{
		$prev_id = 0;
		$next_id = 0;

		$order = $event_at_asc_en ? 'asc' : 'desc';

		$stmt = $this->db->executeQuery('select id
			from ' . $schema . '.news
			where access in (?)
			order by event_at ' . $order . ',created_at desc',
			[$visible_ary],
			[Db::PARAM_STR_ARRAY]);

		$current = false;

		while($id = $stmt->fetchColumn())
		{
            if ($current)
            {
                $next_id = $id;
                break;
			}

			if ($id === $ref_news_id)
			{
				$current = true;
				continue;
			}

			$prev_id = $id;
		}

		return [
			'prev_id'	=> $prev_id,
			'next_id'	=> $next_id,
		];
	}

}
