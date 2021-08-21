<?php declare(strict_types=1);

namespace App\Repository;

use App\Command\News\NewsCommand;
use Doctrine\DBAL\Connection as Db;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NewsRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function get(
		int $id,
		string $schema
	):array
	{
        $news = $this->db->fetchAssociative('select *
            from ' . $schema . '.news
            where id = ?',
			[$id],
			[\PDO::PARAM_INT]
		);

		if (!$news)
		{
			throw new NotFoundHttpException('News with id ' . $id . ' not found');
		}

		return $news;
	}

	public function get_with_prev_next(
		int $id,
		bool $sort_event_at_asc,
		array $visible_ary,
		string $schema
	):array
	{
		$order = $sort_event_at_asc ? 'event_at asc, ' : '';

        $news = $this->db->fetchAssociative('select n.*
			from (select *,
				lag(id) over (order by ' . $order . 'created_at asc) as prev_id,
				lead(id) over (order by ' . $order . 'created_at asc) as next_id
            	from ' . $schema . '.news
				where access in (?)) n
            where n.id = ?',
			[$visible_ary, $id],
			[Db::PARAM_STR_ARRAY, \PDO::PARAM_INT]
		);

		if (!$news)
		{
			throw new NotFoundHttpException('News with id ' . $id . ' not found');
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
		$order = $event_at_asc_en ? 'event_at asc, ' : '';

		$stmt = $this->db->executeQuery('select *
			from ' . $schema . '.news
			where access in (?)
			order by ' . $order . 'created_at desc',
			[$visible_ary],
			[Db::PARAM_STR_ARRAY]);

		return $stmt->fetchAllAssociative();
	}

	public function insert(
		NewsCommand $command,
		int $user_id,
		string $schema
	):int
	{
		$news = [
			'user_id'       => $user_id,
			'content'	    => $command->content,
			'subject'	    => $command->subject,
			'access'        => $command->access,
			'location'		=> $command->location,
			'event_at'		=> $command->event_at,
		];

		$this->db->insert($schema . '.news', $news);
		return (int) $this->db->lastInsertId($schema . '.news_id_seq');
	}

	public function update(
		NewsCommand $command,
		string $schema
	):bool
	{
		$news = [
			'content'	    => $command->content,
			'subject'	    => $command->subject,
			'access'        => $command->access,
			'location'		=> $command->location,
			'event_at'		=> $command->event_at,
		];

		$id = $command->id;

		if (!$id)
		{
			throw new LogicException('no id set');
		}

		return $this->db->update($schema . '.news', $news,
			['id' => $id]) ? true : false;
	}
}
