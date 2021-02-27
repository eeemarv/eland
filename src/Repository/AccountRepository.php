<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class AccountRepository
{
	public function __construct(
        protected Db $db
    )
	{
	}

    public function get_min_limit(int $account_id, string $schema):?int
    {
        return $this->db->fetchOne('select m.min_limit
            from (values(0)) as d
            left join ' . $schema . '.min_limit as m
            on m.account_id = ?
            order by m.id desc
            limit 1',
            [$account_id],
            [\PDO::PARAM_INT]);
    }

    public function update_min_limit(int $account_id, ?int $min_limit, ?int $created_by, string $schema):void
    {
        $created_by = $created_by ?: null;

        $this->db->insert($schema . '.min_limit', [
            'min_limit'     => $min_limit,
            'account_id'    => $account_id,
            'created_by'    => $created_by,
        ]);
    }

    public function get_min_limit_ary(string $schema):array
    {
        $min_limit_ary = [];

        $rs = $this->db->prepare('select distinct on(account_id) min_limit, account_id
            from ' . $schema . '.min_limit
            order by account_id, id desc');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $min_limit_ary[$row['account_id']] = $row['min_limit'];
        }

        return $min_limit_ary;
    }

    public function get_max_limit(int $account_id, string $schema):?int
    {
        return $this->db->fetchOne('select m.max_limit
            from (values(0)) as d
            left join ' . $schema . '.max_limit as m
            on m.account_id = ?
            order by m.id desc
            limit 1',
            [$account_id], [\PDO::PARAM_INT]);
    }

    public function update_max_limit(int $account_id, ?int $max_limit, ?int $created_by, string $schema):void
    {
        $created_by = $created_by ?: null;

        $this->db->insert($schema . '.max_limit', [
            'max_limit'     => $max_limit,
            'account_id'    => $account_id,
            'created_by'    => $created_by,
        ]);
    }

    public function get_max_limit_ary(string $schema):array
    {
        $max_limit_ary = [];

        $rs = $this->db->prepare('select distinct on(account_id) max_limit, account_id
            from ' . $schema . '.max_limit
            order by account_id, id desc');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $max_limit_ary[$row['account_id']] = $row['max_limit'];
        }

        return $max_limit_ary;
    }

    public function get_balance(int $account_id, string $schema):int
    {
        return $this->db->fetchOne('select coalesce(b.balance, 0)
            from (values(0)) as d
            left join ' . $schema . '.balance as b
            on b.account_id = ?
            order by b.id desc
            limit 1',
            [$account_id], [\PDO::PARAM_INT]);
    }

    public function get_balance_on_date(int $account_id, \DateTimeImmutable $datetime, string $schema):int
    {
        return $this->db->fetchOne('select coalesce(b.balance, 0)
            from (values(0)) as d
            left join ' . $schema . '.balance as b
            on b.account_id = ? and b.created_at <= ?
            order by b.id desc
            limit 1',
            [$account_id, $datetime],
            [\PDO::PARAM_INT, Types::DATETIME_IMMUTABLE]);
    }

    public function update_balance(int $account_id, int $amount, string $schema):void
    {
        $this->db->executeStatement('insert into ' . $schema . '.balance (account_id, amount, balance)
            values (?, ?, (select coalesce(b.balance, 0) + ?
            from (values(0)) as d
            left join ' . $schema . '.balance as b
            on b.account_id = ?
            order by b.id desc limit 1))',
            [$account_id, $amount, $amount, $account_id],
            [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]);
    }

    public function get_balance_ary(string $schema):array
    {
        $balance_ary = [];

        $rs = $this->db->prepare('select distinct on(account_id) balance, account_id
            from ' . $schema . '.balance
            order by account_id, id desc');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $balance_ary[$row['account_id']] = $row['balance'];
        }

        return $balance_ary;
    }

    public function get_balance_ary_on_date(\DateTimeImmutable $datetime, string $schema):array
    {
        $balance_ary = [];

        $rs = $this->db->prepare('select distinct on(account_id) balance, account_id
            from ' . $schema . '.balance
            where created_at <= ?
            order by account_id, id desc');

        $rs->bindValue(1, $datetime, Types::DATETIME_IMMUTABLE);

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $balance_ary[$row['account_id']] = $row['balance'];
        }

        return $balance_ary;
    }
}
