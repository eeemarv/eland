<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;

class AccountRepository
{
	protected Db $db;

	public function __construct(Db $db)
	{
		$this->db = $db;
	}

    public function get_min_limit(int $account_id, string $schema):?int
    {
        return $this->db->fetchColumn('select m.min_limit
            from (values(0)) as d
            left join ' . $schema . '.min_limit as m
            on m.account_id = ?
            order by m.id desc
            limit 1', [$account_id]);
    }

    public function get_max_limit(int $account_id, string $schema):?int
    {
        return $this->db->fetchColumn('select m.max_limit
            from (values(0)) as d
            left join ' . $schema . '.max_limit as m
            on m.account_id = ?
            order by m.id desc
            limit 1', [$account_id]);
    }

    public function get_balance(int $account_id, string $schema):int
    {
        return $this->db->fetchColumn('select coalesce(b.balance, 0)
            from (values(0)) as d
            left join ' . $schema . '.balance as b
            on b.account_id = ?
            order by b.id desc
            limit 1', [$account_id]);
    }

    public function update_balance(int $account_id, int $amount, string $schema):void
    {
        $this->db->executeUpdate('insert into ' . $schema . '.balance (account_id, amount, balance)
            values (?, ?, (select coalesce(b.balance, 0) + ?
            from (values(0)) as d
            left join ' . $schema . '.balance as b
            on b.account_id = ?
            order by b.id desc limit 1))',
            [$account_id, $amount, $amount, $account_id],
            [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]);
    }


}
