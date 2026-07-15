<?php declare(strict_types=1);

namespace App\Repository;

use App\Command\Transactions\TransactionsFilterCommand;
use App\DTO\Schema;
use App\Service\SystemsService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Uid\Uuid;

class TransactionRepository
{
	public function __construct(
		private readonly Db $db,
    private readonly SystemsService $systems_service
	)
	{
	}

  // not used yet(?)
	public function get_count_for_user_id(
    int $user_id,
    Schema $schema,
  ):int
	{
    $stmt = $this->db->prepare('select count(*)
      from ' . $schema->str() . '.transactions
      where id_to = :user_id or id_from = :user_id');
		$stmt->bindValue('user_id', $user_id, Types::INTEGER);
		$res = $stmt->executeQuery();
		return $res->fetchOne();
	}

	public function get(
    int $id,
    Schema $schema,
  ):array|false
	{
		$stmt = $this->db->prepare('select *
			from ' . $schema->str() . '.transactions
			where id = :id');
		$stmt->bindValue('id', $id, Types::INTEGER);
		$res = $stmt->executeQuery();
		$data = $res->fetchAssociative();

		return $data;
	}

	public function get_next_id(
    int $id,
    Schema $schema,
  ):int|false
	{
		$stmt = $this->db->prepare('select id
			from ' . $schema->str() . '.transactions
			where id > :id
			order by id asc
			limit 1');
		$stmt->bindValue('id', $id, Types::INTEGER);
		$res = $stmt->executeQuery();
		return $res->fetchOne();
	}

	public function get_prev_id(
    int $id,
    Schema $schema,
  ):int|false
	{
		$stmt = $this->db->prepare('select id
			from ' . $schema->str() . '.transactions
			where id < :id
			order by id desc
			limit 1');
		$stmt->bindValue('id', $id, Types::INTEGER);
		$res = $stmt->executeQuery();
		return $res->fetchOne();
	}

	public function update_description(
    int $id,
    string $description,
    Schema $schema,
  )
	{
		$this->db->update($schema->str() . '.transactions', [
      'description'	=> $description,
    ], [
      'id' => $id,
    ], [
      'description' => Types::STRING,
      'id'  => Types::INTEGER,
    ]);
	}

  public function get_activity_for_each_user(
    \DateTimeImmutable $since,
    int|null $exclude_account_id,
    Schema $schema,
  ):array
  {
    $ary = [];
    // an not existing value when not set
    $exclude_id = $exclude_account_id ?? -1;
    $stmt = $this->db->prepare('with a_transactions as (
      select id_to as user_id,
        \'in\' as direction,
        amount
      from ' . $schema->str() . '.transactions
      where created_at >= :since
        and id_from <> :exclude_id
      union all
      select id_from as user_id,
        \'out\' as direction,
        amount
      from ' . $schema->str() . '.transactions
      where created_at >= :since
        and id_to <> :exclude_id
      )
      select user_id,
        count(*) as trans_total,
        count(*) filter (where direction = \'in\') as trans_in,
        count(*) filter (where direction = \'out\') as trans_out,
        sum(amount) as amount_total,
        sum(amount) filter (where direction = \'in\') as amount_in,
        sum(amount) filter (where direction = \'out\') as amount_out
      from a_transactions
      group by user_id');
    $stmt->bindValue('since', $since, Types::DATETIME_IMMUTABLE);
    $stmt->bindValue('exclude_id', $exclude_id, Types::INTEGER);
    $res = $stmt->executeQuery();
    while ($row = $res->fetchAssociative())
    {
      $ary[$row['user_id']] = $row;
    }
    return $ary;
  }

  public function get_filtered_transactions(
    TransactionsFilterCommand $filter_command,
    int $start,
    int $limit,
    string $order_by,
    bool $asc,
    Schema $schema,
  ):array
  {
    $allowed_sort_cols = [
      'amount'      => 't',
      'description' => 't',
      'to_code'     => '',
      'from_code'   => '',
      'created_at'  => 't',
    ];

    if (!isset($allowed_sort_cols[$order_by]))
    {
      throw new \Exception('Not allowed order_by ' . $order_by);
    }

    $prefixed_order_by = '';
    if ($allowed_sort_cols[$order_by])
    {
      $prefixed_order_by .= $allowed_sort_cols[$order_by];
      $prefixed_order_by .= '.';
    }
    $prefixed_order_by .= $order_by;

    $sql = [
      'where'     => [
        'common'  => '1 = 1',
      ],
      'params'    => [],
      'types'     => [],
    ];

    if (isset($filter_command->q))
    {
      $sql['where']['q'] = 't.description ilike :q';
      $sql['params']['q'] = '%' . $filter_command->q . '%';
      $sql['types']['q'] = Types::STRING;
    }

    $accounts_eq = isset($filter_command->account_logic)
      && $filter_command->account_logic === 'nor' ? '<>' : '=';

    if (isset($filter_command->from_account))
    {
      $sql['where']['from_account'] = 't.id_from ' . $accounts_eq . ' :from_account';
      $sql['params']['from_account'] = $filter_command->from_account;
      $sql['types']['from_account'] = Types::INTEGER;
    }

    if (isset($filter_command->to_account))
    {
      $sql['where']['to_account'] = 't.id_to ' . $accounts_eq . ' :to_account';
      $sql['params']['to_account'] = $filter_command->to_account;
      $sql['types']['to_account'] = Types::INTEGER;
    }

    if (isset($filter_command->account_logic)
      && $filter_command->account_logic === 'or'
      && isset($filter_command->from_account)
      && isset($filter_command->to_account)
    )
    {
      $sql['where']['from_account'] =
        '(t.id_from = :from_account or t.id_to = :to_account)';
      unset($sql['where']['to_account']);
    }

    if (isset($filter_command->from_date))
    {
      $from_date = \DateTimeImmutable::createFromFormat('U', (string) strtotime($filter_command->from_date . ' UTC'));
      $sql['where']['from_date'] = 't.created_at >= :from_date';
      $sql['params']['from_date'] = $from_date;
      $sql['types']['from_date'] = Types::DATETIME_IMMUTABLE;
    }

    if (isset($filter_command->to_date))
    {
      $to_date = \DateTimeImmutable::createFromFormat('U', (string) strtotime($filter_command->to_date . ' UTC'));
      $sql['where']['to_date'] = 't.created_at <= :to_date';
      $sql['params']['to_date'] = $to_date;
      $sql['types']['to_date'] = Types::DATETIME_IMMUTABLE;
    }

    if (isset($filter_command->srvc))
    {
      $srvc_where_or = [];

      if (in_array('srvc', $filter_command->srvc))
      {
        $srvc_where_or[] = 't.service_stuff = \'service\'';
      }

      if (in_array('stff', $filter_command->srvc))
      {
        $srvc_where_or[] = 't.service_stuff = \'stuff\'';
      }

      if (in_array('null', $filter_command->srvc))
      {
        $srvc_where_or[] = 't.service_stuff is null';
      }

      if (count($srvc_where_or))
      {
        $sql['where']['service_stuff'] = '(' . implode(' or ', $srvc_where_or) . ')';
      }
    }

    $sql['params']['limit'] = $limit;
    $sql['types']['limit'] = Types::INTEGER;
    $sql['params']['offset'] = $start;
    $sql['types']['offset'] = Types::INTEGER;

    $sql_where = implode(' and ', $sql['where']);

    $transactions = [];

    $query = 'select t.*,
      fu.name as from_name,
      fu.code as from_code,
      case
        when fu.status in (1,2) or fu.is_active
          then true
          else false
        end as from_is_active,
      case
        when tu.status in (1,2) or tu.is_active
          then true
          else false
        end as to_is_active,
      fu.remote_schema as from_remote_schema,
      fu.remote_email as from_remote_email,
      tu.name as to_name,
      tu.code as to_code,
      tu.remote_schema as to_remote_schema,
      tu.remote_email as to_remote_email
      from ' . $schema->str() . '.transactions t
        inner join ' . $schema->str() . '.users fu
          on fu.id = t.id_from
        inner join ' . $schema->str() . '.users tu
          on tu.id = t.id_to
      where ' . $sql_where . '
      order by ' . $prefixed_order_by .
      ($asc ? ' asc' : ' desc') . '
      limit :limit offset :offset';

    $res = $this->db->executeQuery($query,
      $sql['params'], $sql['types']);

    $transactions = [];
    $inter_fetch = [];
    $inter_ary = [];

    while ($row = $res->fetchAssociative())
    {
      $transactions[$row['id']] = $row;
      if (!isset($row['remote_ref']))
      {
        continue;
      }
      if (isset($row['from_remote_schema']))
      {
        if (!isset($inter_fetch[$row['from_remote_schema']]))
        {
          $inter_fetch[$row['from_remote_schema']] = [];
        }
        $inter_fetch[$row['from_remote_schema']][] = $row['remote_ref'];
        continue;
      }
      if (isset($row['to_remote_schema']))
      {
        if (!isset($inter_fetch[$row['to_remote_schema']]))
        {
          $inter_fetch[$row['to_remote_schema']] = [];
        }
        $inter_fetch[$row['to_remote_schema']][] = $row['remote_ref'];
        continue;
      }
    }

    foreach ($inter_fetch as $i_schema => $remote_ref_ary)
    {
      if (!$this->systems_service->has_schema($i_schema))
      {
        continue;
      }
      $i_query = 'select t.*,
        fu.name as from_name,
        fu.code as from_code,
        fu.remote_schema as from_remote_schema,
        case
          when fu.status in (1,2) or fu.is_active
          then true
          else false
          end as from_is_active,
        case
          when tu.status in (1,2) or tu.is_active
          then true
          else false
          end as to_is_active,
        tu.name as to_name,
        tu.code as to_code,
        tu.remote_schema as to_remote_schema
        from ' . $i_schema . '.transactions t
          inner join ' . $i_schema . '.users fu
            on fu.id = t.id_from
          inner join ' . $i_schema . '.users tu
            on tu.id = t.id_to
        where t.remote_ref in (:remote_ref_ary)';
      $res = $this->db->executeQuery($i_query, [
          'remote_ref_ary'  => $remote_ref_ary,
        ], [
          'remote_ref_ary'  => ArrayParameterType::STRING,
        ]
      );
      while ($row = $res->fetchAssociative())
      {
        if (!isset($inter_ary[$i_schema]))
        {
          $inter_ary[$i_schema] = [];
        }
        $inter_ary[$i_schema][$row['remote_ref']] = $row;
      }
    }

    $sql_all = $sql;
    unset($sql_all['params']['limit']);
    unset($sql_all['types']['limit']);
    unset($sql_all['params']['offset']);
    unset($sql_all['types']['offset']);

    $sql_where_intersystem = $sql_all['where'];
    $sql_where_intersystem['intersystem'] = '(t.real_from is not null or t.real_to is not null)';
    $sql_where_intersystem = implode(' and ', $sql_where_intersystem);

    $sql_where_confirmed = $sql_all['where'];
    $sql_where_confirmed['status'] = 't.is_confirmed';
    $sql_where_confirmed = implode(' and ', $sql_where_confirmed);

    $sql_where_pending = $sql_all['where'];
    $sql_where_pending['status'] = 'not t.is_confirmed and not t.is_canceled and not t.is_expired';
    $sql_where_pending = implode(' and ', $sql_where_pending);

    $sql_where_canceled = $sql_all['where'];
    $sql_where_canceled['status'] = 't.is_canceled';
    $sql_where_canceled = implode(' and ', $sql_where_canceled);

    $sql_where_expired = $sql_all['where'];
    $sql_where_expired['status'] = 't.is_expired';
    $sql_where_expired = implode(' and ', $sql_where_expired);

    $sql_where_service = $sql_all['where'];
    $sql_where_service['service'] = 't.service_stuff = \'service\'';
    $sql_where_service = implode(' and ', $sql_where_service);

    $sql_where_stuff = $sql_all['where'];
    $sql_where_stuff['stuff'] = 't.service_stuff = \'stuff\'';
    $sql_where_stuff = implode(' and ', $sql_where_stuff);

    $sql_where_null_service_stuff = $sql_all['where'];
    $sql_where_null_service_stuff['stuff'] = 't.service_stuff is null';
    $sql_where_null_service_stuff = implode(' and ', $sql_where_null_service_stuff);

    $count_ary = $this->db->fetchAssociative('select
      count(t.*) filter
        (where ' . $sql_where . ') as row_count,
      sum(t.amount) filter
        (where ' . $sql_where . ') as amount_sum,
      count(distinct t.id_from) filter
        (where ' . $sql_where . ') as from_accounts,
      count(distinct t.id_to) filter
        (where ' . $sql_where . ') as to_accounts,
      count(t.*) filter
        (where ' . $sql_where_intersystem . ') as intersystem,
      count(t.*) filter
        (where ' . $sql_where_confirmed . ') as confirmed,
      count(t.*) filter
        (where ' . $sql_where_pending . ') as pending,
      count(t.*) filter
        (where ' . $sql_where_canceled . ') as canceled,
      count(t.*) filter
        (where ' . $sql_where_expired . ') as expired,
      count(t.*) filter
        (where ' . $sql_where_service . ') as service,
      count(t.*) filter
        (where ' . $sql_where_stuff . ') as stuff,
      count(t.*) filter
        (where ' . $sql_where_null_service_stuff . ') as null_service_stuff
      from ' . $schema->str() . '.transactions t
      inner join ' . $schema->str() . '.users fu
        on fu.id = t.id_from
      inner join ' . $schema->str() . '.users tu
        on tu.id = t.id_to',
      $sql_all['params'],
      $sql_all['types']);

    $count_ary['total_accounts'] = $this->db->fetchOne('
      with all_accounts as (
        select t.id_from as user_id
        from ' . $schema->str() . '.transactions t
        inner join ' . $schema->str() . '.users fu
          on fu.id = t.id_from
        inner join ' . $schema->str() . '.users tu
          on tu.id = t.id_to
        where ' . $sql_where . '
      union
        select t.id_to as user_id
        from ' . $schema->str() . '.transactions t
        inner join ' . $schema->str() . '.users fu
          on fu.id = t.id_from
        inner join ' . $schema->str() . '.users tu
          on tu.id = t.id_to
        where ' . $sql_where . '
      )
      select count(*) as total_accounts_count
      from all_accounts',
      $sql_all['params'],
      $sql_all['types'],
    );

    return [
      'transactions'  => $transactions,
      'inter_ary' => $inter_ary,
      'count_ary' => $count_ary,
    ];
  }

  public function set_bulk_service_stuff(
    string $service_stuff,
    array $transaction_ids,
    Schema $schema,
  ):void
  {
    if ($service_stuff === 'null_service_stuff')
    {
      $this->db->executeStatement('update ' .
        $schema->str() . '.transactions
        set service_stuff = null
        where id in (:transaction_ids)', [
          'transaction_ids' => $transaction_ids,
        ], [
          'transaction_ids' => ArrayParameterType::INTEGER,
        ]);
        return;
    }
    $this->db->executeStatement('update ' .
      $schema->str() . '.transactions
      set service_stuff = :service_stuff
      where id in (:transaction_ids)', [
        'service_stuff' => $service_stuff,
        'transaction_ids' => $transaction_ids,
      ], [
        'service_stuff' => Types::STRING,
        'transaction_ids' => ArrayParameterType::INTEGER,
      ]);
      return;
  }

  public function insert(
    int $from_account_id,
    int $to_account_id,
    int $amount,
    string $description,
    string|null $service_stuff,
    int|null $created_by,
    int|null $autominlimit_percentage,
    int|null $global_min_limit,
    Schema $schema,
  ): void
  {
    $this->db->beginTransaction();

    try {
      $stmt_ins = $this->db->prepare('
        insert into ' . $schema->str() . '.transactions
        (id_from, id_to, amount, description,
          service_stuff, created_by)
        values (:id_from, :id_to, :amount, :description,
          :service_stuff, :created_by)');

      $stmt_ins->bindValue('id_from', $from_account_id, Types::INTEGER);
      $stmt_ins->bindValue('id_to', $to_account_id, Types::INTEGER);
      $stmt_ins->bindValue('amount', $amount, Types::INTEGER);
      $stmt_ins->bindValue('description', $description, Types::STRING);
      $stmt_ins->bindValue('service_stuff', $service_stuff, Types::STRING);
      $stmt_ins->bindValue('created_by', $created_by, Types::INTEGER);

      $stmt_ins->executeStatement();

      $transaction_id = (int) $this->db->lastInsertId();

      $stmt_bal = $this->db->prepare('
        insert into ' . $schema->str() . '.balance
        (account_id, amount,
        transaction_id, created_by,
        balance)
        values (:account_id, :amount,
          :transaction_id, :created_by,
          (select coalesce(b.balance, 0) + :amount
        from (values(0)) as d
          left join ' . $schema->str() . '.balance as b
            on b.account_id = :account_id
            order by b.id desc limit 1))');

      $stmt_bal->bindValue('account_id', $from_account_id, Types::INTEGER);
      $stmt_bal->bindValue('amount', -$amount, Types::INTEGER); // Negative because it is leaving the account
      $stmt_bal->bindValue('transaction_id', $transaction_id, Types::INTEGER);
      $stmt_bal->bindValue('created_by', $created_by, Types::INTEGER);
      $stmt_bal->executeStatement();

      $stmt_bal->bindValue('account_id', $to_account_id, Types::INTEGER);
      $stmt_bal->bindValue('amount', $amount, Types::INTEGER); // Negative because it is leaving the account
      $stmt_bal->bindValue('transaction_id', $transaction_id, Types::INTEGER);
      $stmt_bal->bindValue('created_by', $created_by, Types::INTEGER);
      $stmt_bal->executeStatement();

      if (isset($autominlimit_percentage))
      {
        $autominlimit_amount = (int) round($amount * ($autominlimit_percentage / 100));

        if ($autominlimit_amount)
        {
          $stmt_min = $this->db->prepare('select m.min_limit
            from (values(0)) as d
            left join ' . $schema->str() . '.min_limit as m
            on m.account_id = :account_id
            order by m.id desc
            limit 1');
          $stmt_min->bindValue('account_id', $to_account_id, Types::INTEGER);
          $res_min = $stmt_min->executeQuery();
          $min_limit = $res_min->fetchOne();
          if (isset($min_limit))
          {
            $new_min_limit = $min_limit - $autominlimit_amount;
            if (isset($global_min_limit)
              && ($new_min_limit <= $global_min_limit))
            {
              $new_min_limit = null;
            }
            $stmt_min_in = $this->db->prepare('insert into ' . $schema->str() . '.min_limit
              (account_id, min_limit,
              created_by, auto_transaction_id,
              is_auto)
              values(:account_id, :min_limit,
              :created_by, :auto_transaction_id, true)');
            $stmt_min_in->bindValue('account_id', $to_account_id, Types::STRING);
            $stmt_min_in->bindValue('min_limit', $new_min_limit, Types::STRING);
            $stmt_min_in->bindValue('created_by', $created_by, Types::INTEGER);
            $stmt_min_in->bindValue('auto_transaction_id', $transaction_id ?? null, Types::INTEGER);
            $stmt_min_in->executeStatement();
          }
        }
      }

      $this->db->commit();
    }
    catch (\Exception $e)
    {
      $this->db->rollBack();
      throw $e;
    }
  }

  public function insert_mass_many_to_one(
    array $from_account_ids_amounts,
    int $to_account_id,
    string $description,
    string|null $service_stuff,
    int|null $created_by,
    int|null $autominlimit_percentage,
    int|null $global_min_limit,
    Schema $schema,
  ): void
  {
    $bulk_id = Uuid::v4()->toRfc4122();

    $this->db->beginTransaction();

    try {
      $stmt_ins = $this->db->prepare('
        insert into ' . $schema->str() . '.transactions
        (id_from, id_to, amount, description,
          service_stuff, created_by, bulk_id)
        values (:id_from, :id_to, :amount, :description,
          :service_stuff, :created_by, :bulk_id)');

      $stmt_bal = $this->db->prepare('
          insert into ' . $schema->str() . '.balance
          (account_id, amount,
          transaction_id, created_by,
          balance)
          values (:account_id, :amount,
            :transaction_id, :created_by,
            (select coalesce(b.balance, 0) + :amount
          from (values(0)) as d
            left join ' . $schema->str() . '.balance as b
              on b.account_id = :account_id
              order by b.id desc limit 1))');

      foreach ($from_account_ids_amounts as $from_id => $amount)
      {
        $stmt_ins->bindValue('id_from', $from_id, Types::INTEGER);
        $stmt_ins->bindValue('id_to', $to_account_id, Types::INTEGER);
        $stmt_ins->bindValue('amount', $amount, Types::INTEGER);
        $stmt_ins->bindValue('description', $description, Types::STRING);
        $stmt_ins->bindValue('service_stuff', $service_stuff, Types::STRING);
        $stmt_ins->bindValue('created_by', $created_by, Types::INTEGER);
        $stmt_ins->bindValue('bulk_id', $bulk_id, Types::GUID);

        $stmt_ins->executeStatement();

        $transaction_id = (int) $this->db->lastInsertId();

        $stmt_bal->bindValue('account_id', $from_id, Types::INTEGER);
        $stmt_bal->bindValue('amount', -$amount, Types::INTEGER); // Negative because it is leaving the account
        $stmt_bal->bindValue('transaction_id', $transaction_id, Types::INTEGER);
        $stmt_bal->bindValue('created_by', $created_by, Types::INTEGER);
        $stmt_bal->executeStatement();
      }

      $amount_sum = array_sum($from_account_ids_amounts);

      $stmt_bal->bindValue('account_id', $to_account_id, Types::INTEGER);
      $stmt_bal->bindValue('amount', $amount_sum, Types::INTEGER);
      $stmt_bal->bindValue('transaction_id', $transaction_id ?? null, Types::INTEGER); // record just the last transaction_id
      $stmt_bal->bindValue('created_by', $created_by, Types::INTEGER);
      $stmt_bal->executeStatement();

      if (isset($autominlimit_percentage))
      {
        $autominlimit_amount = (int) round($amount_sum * ($autominlimit_percentage / 100));

        if ($autominlimit_amount)
        {
          $stmt_min = $this->db->prepare('select m.min_limit
            from (values(0)) as d
            left join ' . $schema->str() . '.min_limit as m
            on m.account_id = :account_id
            order by m.id desc
            limit 1');
          $stmt_min->bindValue('account_id', $to_account_id, Types::INTEGER);
          $res_min = $stmt_min->executeQuery();
          $min_limit = $res_min->fetchOne();
          if (isset($min_limit))
          {
            $new_min_limit = $min_limit - $autominlimit_amount;
            if (isset($global_min_limit)
              && ($new_min_limit <= $global_min_limit))
            {
              $new_min_limit = null;
            }
            $stmt_min_in = $this->db->prepare('insert into ' . $schema->str() . '.min_limit
              (account_id, min_limit,
              created_by, auto_transaction_id,
              is_auto)
              values(:account_id, :min_limit,
              :created_by, :auto_transaction_id, true)');
            $stmt_min_in->bindValue('account_id', $to_account_id, Types::STRING);
            $stmt_min_in->bindValue('min_limit', $new_min_limit, Types::STRING);
            $stmt_min_in->bindValue('created_by', $created_by, Types::INTEGER);
            $stmt_min_in->bindValue('auto_transaction_id', $transaction_id ?? null, Types::INTEGER);
            $stmt_min_in->executeStatement();
          }
        }
      }

      $this->db->commit();
    }
    catch (\Exception $e)
    {
      $this->db->rollBack();
      throw $e;
    }
  }

  public function insert_mass_one_to_many(
    int $from_account_id,
    array $to_account_ids_amounts,
    string $description,
    string|null $service_stuff,
    int|null $created_by,
    int|null $autominlimit_percentage,
    int|null $global_min_limit,
    Schema $schema,
  ): void
  {
    $bulk_id = Uuid::v4()->toRfc4122();

    $this->db->beginTransaction();

    try {
      $stmt_ins = $this->db->prepare('
        insert into ' . $schema->str() . '.transactions
        (id_from, id_to, amount, description,
          service_stuff, created_by, bulk_id)
        values (:id_from, :id_to, :amount, :description,
          :service_stuff, :created_by, :bulk_id)');

      $stmt_bal = $this->db->prepare('
          insert into ' . $schema->str() . '.balance
          (account_id, amount,
          transaction_id, created_by,
          balance)
          values (:account_id, :amount,
            :transaction_id, :created_by,
            (select coalesce(b.balance, 0) + :amount
          from (values(0)) as d
            left join ' . $schema->str() . '.balance as b
              on b.account_id = :account_id
              order by b.id desc limit 1))');

      $stmt_min = $this->db->prepare('select m.min_limit
        from (values(0)) as d
        left join ' . $schema->str() . '.min_limit as m
        on m.account_id = :account_id
        order by m.id desc
        limit 1');

      $stmt_min_in = $this->db->prepare('insert into ' . $schema->str() . '.min_limit
        (account_id, min_limit,
        created_by, auto_transaction_id,
        is_auto)
        values(:account_id, :min_limit,
        :created_by, :auto_transaction_id, true)');

      foreach ($to_account_ids_amounts as $to_id => $amount)
      {
        $stmt_ins->bindValue('id_from', $from_account_id, Types::INTEGER);
        $stmt_ins->bindValue('id_to', $to_id, Types::INTEGER);
        $stmt_ins->bindValue('amount', $amount, Types::INTEGER);
        $stmt_ins->bindValue('description', $description, Types::STRING);
        $stmt_ins->bindValue('service_stuff', $service_stuff, Types::STRING);
        $stmt_ins->bindValue('created_by', $created_by, Types::INTEGER);
        $stmt_ins->bindValue('bulk_id', $bulk_id, Types::GUID);

        $stmt_ins->executeStatement();

        $transaction_id = (int) $this->db->lastInsertId();

        $stmt_bal->bindValue('account_id', $to_id, Types::INTEGER);
        $stmt_bal->bindValue('amount', $amount, Types::INTEGER);
        $stmt_bal->bindValue('transaction_id', $transaction_id, Types::INTEGER);
        $stmt_bal->bindValue('created_by', $created_by, Types::INTEGER);
        $stmt_bal->executeStatement();

        if (isset($autominlimit_percentage))
        {
          $autominlimit_amount = (int) round($amount * ($autominlimit_percentage / 100));

          if ($autominlimit_amount)
          {
            $stmt_min->bindValue('account_id', $to_id, Types::INTEGER);
            $res_min = $stmt_min->executeQuery();
            $min_limit = $res_min->fetchOne();
            if (isset($min_limit))
            {
              $new_min_limit = $min_limit - $autominlimit_amount;
              if (isset($global_min_limit)
                && ($new_min_limit <= $global_min_limit))
              {
                $new_min_limit = null;
              }
              $stmt_min_in->bindValue('account_id', $to_id, Types::INTEGER);
              $stmt_min_in->bindValue('min_limit', $new_min_limit, Types::INTEGER);
              $stmt_min_in->bindValue('created_by', $created_by, Types::INTEGER);
              $stmt_min_in->bindValue('auto_transaction_id', $transaction_id ?? null, Types::INTEGER);
              $stmt_min_in->executeStatement();
            }
          }
        }
      }

      $amount_sum = array_sum($to_account_ids_amounts);

      $stmt_bal->bindValue('account_id', $from_account_id, Types::INTEGER);
      $stmt_bal->bindValue('amount', -$amount_sum, Types::INTEGER);
      $stmt_bal->bindValue('transaction_id', $transaction_id ?? null, Types::INTEGER); // record just the last transaction_id
      $stmt_bal->bindValue('created_by', $created_by, Types::INTEGER);
      $stmt_bal->executeStatement();

      $this->db->commit();
    }
    catch (\Exception $e)
    {
      $this->db->rollBack();
      throw $e;
    }
  }

  public function insert_invitation()
  {

  }


}
