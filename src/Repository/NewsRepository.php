<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use App\Service\Xdb;
use App\Service\XdbAccess;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NewsRepository
{
	private $db;
	private $xdb;
	private $xdbAccess;

	public function __construct(Db $db, Xdb $xdb, XdbAccess $xdbAccess)
	{
		$this->db = $db;
		$this->xdb = $xdb;
		$this->xdbAccess = $xdbAccess;
	}

	public function getAll(string $schema)
	{

	}

	public function get(int $id, string $schema):array
	{
		$data = $this->db->fetchAssoc('select *
			from ' . $schema . '.news 
			where id = ?', [$id]);
	
		if (!$data)
		{
			throw new NotFoundHttpException(sprintf(
				'News %d does not exist in %s', 
				$id, __CLASS__));
		}
		
		$row = $this->xdb->get('news_access', $id, $schema);

		if (!count($row))	
		{
			$row['data']['access'] = 'interlets';
			$this->xdb->set('news_access', $id, $schema, ['access' => 'interlets']);
		}

		$data['access'] = $row['data']['access'];	
			
		return $data;
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
