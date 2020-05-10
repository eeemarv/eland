<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Util\Pagination;
use App\Util\Sort;
use App\Filter\TransactionFilter;
use App\Filter\FilterQuery;

class TransactionRepository
{
	private $db;

	public function __construct(db $db)
	{
		$this->db = $db;
	}

	public function getAll(Pagination $pagination, string $schema):array
	{

	}

	public function getFiltered(string $schema, FilterQuery $filterQuery, Sort $sort, Pagination $pagination):array
	{
		$query = 'select t.* from ' . $schema . '.transactions t';
		$query .= $filterQuery->getWhereQueryString();
		$query .= $sort->query();
		$query .= $pagination->query();

		$transactions = [];

		$rs = $this->db->executeQuery($query, $filterQuery->getParams());

		while ($row = $rs->fetch())
		{
			if ($row['real_to'] || $row['real_from'])
			{
				$row['class'] = 'table-warning';
			}

			$transactions[] = $row;
		}

		foreach ($transactions as $key => $t)
		{
			if (!($t['real_from'] || $t['real_to']))
			{
				continue;
			}

			$inter_schema = false;

			if (isset($interlets_accounts_schemas[$t['id_from']]))
			{
				$inter_schema = $interlets_accounts_schemas[$t['id_from']];
			}
			else if (isset($interlets_accounts_schemas[$t['id_to']]))
			{
				$inter_schema = $interlets_accounts_schemas[$t['id_to']];
			}

			if ($inter_schema)
			{
				$inter_transaction = $db->fetchAssoc('select t.*
					from ' . $inter_schema . '.transactions t
					where t.transid = ?', [$t['transid']]);

				if ($inter_transaction)
				{
					$transactions[$key]['inter_schema'] = $inter_schema;
					$transactions[$key]['inter_transaction'] = $inter_transaction;
				}
			}
		}

		return $transactions;
	}

	public function getFilteredRowCount(string $schema, FilterQuery $filterQuery):int
	{
		$query = 'select count(t.*) from ' . $schema . '.transactions t';
		$query .= $filterQuery->getWhereQueryString();
		return $this->db->fetchColumn($query, $filterQuery->getParams());
	}

	public function get(int $id, string $schema):array
	{
		$data = $this->db->fetchAssoc('select *
			from ' . $schema . '.transactions
			where id = ?', [$id]);

		if (!$data)
		{
			throw new NotFoundHttpException(sprintf('Transaction %d does not exist in %s',
				$id, __CLASS__));
		}

		return $data;
	}

	public function getNext(int $id, string $schema)
	{
		return $this->db->fetchColumn('select id
			from ' . $schema . '.transactions
			where id > ?
			order by id asc
			limit 1', [$id]) ?? null;
	}

	public function getPrev(int $id, string $schema)
	{
		return $this->db->fetchColumn('select id
			from ' . $schema . '.transactions
			where id < ?
			order by id desc
			limit 1', [$id]) ?? null;
	}

	public function updateDescription(int $id, string $description, string $schema)
	{
		$this->db->update($schema . '.transactions', ['description'	=> $description], ['id' => $id]);
	}
}
