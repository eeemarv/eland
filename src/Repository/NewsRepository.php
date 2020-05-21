<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NewsRepository
{
	protected Db $db;

	public function __construct(Db $db)
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

	public function insert(string $schema, array $data):int
	{
		$data['cdate'] = gmdate('Y-m-d H:i:s');
		$data['id_user'] = 1;//($s_master) ? 0 : $s_id;
		$data['approved'] = $data['approved'] ? 't' : 'f';
		$data['sticky'] = $data['sticky'] ? 't' : 'f';
		$data['published'] = 't';

		$access = $data['access'];
		unset($data['access']);

		$this->db->insert($schema . '.news', $data);
		$id = $this->db->lastInsertId($schema . '.news_id_seq');

		$this->xdb->set('news_access', $id, $schema, ['access' => $access]);

		return $id;
	}

	public function update(int $id, string $schema, array $data)
	{
		$data['sticky'] = $data['sticky'] ? 't' : 'f';
		$data['approved'] = $data['approved'] ? 't' : 'f';
		$data['published'] = 't';
		$access = $data['access'];
		unset($data['access']);

		$this->db->update($schema . '.news', $data, ['id' => $id]);
		$this->xdb->set('news_access', $id, $schema, ['access' => $access]);
	}

	public function approve(int $id, string $schema)
	{
		$this->db->update($schema . '.news', ['approved' => 't'], ['id' => $id]);
	}

	public function delete(int $id, string $schema)
	{
		$this->db->delete($schema . '.news', ['id' => $id]);
		$this->xdb->del('news_access', $id, $schema);
	}

	public function getNext(int $id, string $schema, string $access)
	{
		$rows = $this->xdb->getFiltered([
			'agg_schema' 	=> $schema,
			'agg_type' 		=> 'news_access',
			'eland_id' 		=> ['>' => $id],
			'access' 		=> $this->xdbAccess->get($access),
		],
		'order by eland_id asc limit 1');

		return count($rows) ? reset($rows)['eland_id'] : null;
	}

	public function getPrev(int $id, string $schema, string $access)
	{
		$rows = $this->xdb->getFiltered([
			'agg_schema' => $schema,
			'agg_type' => 'news_access',
			'eland_id' => ['<' => $id],
			'access' => $this->xdbAccess->get($access),
		], 'order by eland_id desc limit 1');

		return count($rows) ? reset($rows)['eland_id'] : null;
	}
}
