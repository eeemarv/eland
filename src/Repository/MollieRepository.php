<?php declare(strict_types=1);

namespace App\Repository;

use App\Command\Mollie\MollieFilterCommand;
use App\DTO\Schema;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Uid\Uuid;

class MollieRepository
{
	public function __construct(
		private readonly Db $db
	)
	{
	}

  public function insert_payment_requests(
    string $description,
    int|null $created_by,
    array $user_amount_ary,
    Schema $schema
  ):void
  {
    $stmt_1 = $this->db->prepare('insert into ' .
      $schema->str() . '.mollie_payment_requests
      (description, created_by)
      values
      (:description, :created_by)');
    $stmt_1->bindValue('description', $description, Types::STRING);
    $stmt_1->bindValue('created_by', $created_by, Types::INTEGER);
    $stmt_1->executeStatement();

    $request_id = (int) $this->db->lastInsertId($schema->str() . '.mollie_payment_requests_id_seq');

    $stmt_2 = $this->db->prepare('insert into ' .
      $schema->str() . '.mollie_payments
      (request_id, amount, user_id, currency, created_by)
      values
      (:request_id, :amount, :user_id, :currency, :created_by)');
    $stmt_2->bindValue('request_id', $request_id, Types::INTEGER);
    $stmt_2->bindValue('currency', 'EUR', Types::STRING);
    $stmt_2->bindValue('created_by', $created_by, Types::INTEGER);

    foreach($user_amount_ary as $user_id => $amount)
    {
      $stmt_2->bindValue('amount', strtr($amount, ',', '.'), Types::DECIMAL);
      $stmt_2->bindValue('user_id', $user_id, Types::INTEGER);
      $stmt_2->executeStatement();
    }
  }

	public function get_payment(
		Uuid $checkout_token,
		Schema $schema
	):array|false
	{
		$stmt = $this->db->prepare('select p.*, r.description, u.code
			from ' . $schema->str() . '.mollie_payments p,
				' . $schema->str() . '.mollie_payment_requests r,
				' . $schema->str() . '.users u
			where p.request_id = r.id
				and u.id = p.user_id
				and p.checkout_token = :checkout_token');
    $stmt->bindValue('checkout_token', $checkout_token->toRfc4122(), Types::GUID);
    $res = $stmt->executeQuery();
    return $res->fetchAssociative();
	}

	public function update_mollie_payment_id(
		Uuid $checkout_token,
		string $mollie_payment_id,
		Schema $schema
	):void
	{
    $stmt = $this->db->prepare('update ' . $schema->str() . '.mollie_payments
      set mollie_payment_id = :mollie_payment_id
      where checkout_token = :checkout_token');
    $stmt->bindValue('mollie_payment_id', $mollie_payment_id, Types::INTEGER);
    $stmt->bindValue('checkout_token', $checkout_token->toRfc4122(), Types::GUID);
    $stmt->executeStatement();
	}

	public function set_paid(
		Uuid $checkout_token,
		string $mollie_status,
		Schema $schema
	):void
	{
    $stmt = $this->db->prepare('update ' . $schema->str() . '.mollie_payments
      set is_paid = true,
        mollie_status = :mollie_status
      where checkout_token = :checkout_token');
    $stmt->bindValue('mollie_status', $mollie_status, Types::STRING);
    $stmt->bindValue('checkout_token', $checkout_token->toRfc4122(), Types::GUID);
    $stmt->executeStatement();
	}

	public function get_open_payments_for_user(
		int $user_id,
		Schema $schema
	):array
	{
		$stmt = $this->db->prepare('select p.amount,
      p.checkout_token, r.description
			from ' . $schema->str() . '.mollie_payments p,
				' . $schema->str() . '.mollie_payment_requests r
			where p.request_id = r.id
				and user_id = :user_id
				and not is_canceled
				and not is_paid');
    $stmt->bindValue('user_id', $user_id, Types::INTEGER);
    $res = $stmt->executeQuery();
    return $res->fetchAllAssociative();
	}

  public function get_payment_with_email_addresses(
    int $payment_id,
    Schema $schema
  ):array|false
  {
    $res = $this->db->executeQuery('select p.*,
      u.code, u.name,
      r.description,
      coalesce(jsonb_agg(c.value) filter(where c.value is not null), \'[]\') as email_addresses
      from ' . $schema->str() . '.mollie_payments p
      inner join ' . $schema->str() . '.mollie_payment_requests r
        on p.request_id = r.id
      inner join ' . $schema->str() . '.users u
        on p.user_id = u.id
      left join ' . $schema->str() . '.contact c
        on c.user_id = u.id
          and c.id_type_contact = (select t.id
            from ' . $schema->str() . '.type_contact t
            where t.abbrev = \'mail\')
      where p.id = :payment_id
      group by p.id, u.code, u.name, r.description', [
        'payment_id' => $payment_id,
      ], [
        'payment_id' => Types::INTEGER,
      ]);

    $row = $res->fetchAssociative();

    if (!$row)
    {
      return false;
    }

    $row['email_addresses'] = json_decode($row['email_addresses']);

    return $row;
  }

  public function get_payments_with_email_addresses(
    array $payment_ids,
    Schema $schema
  ):array
  {
    $payments = [];

    $res = $this->db->executeQuery('select p.*,
      u.code, u.name,
      r.description,
      coalesce(jsonb_agg(c.value) filter(where c.value is not null), \'[]\') as email_addresses
      from ' . $schema->str() . '.mollie_payments p
      inner join ' . $schema->str() . '.mollie_payment_requests r
        on p.request_id = r.id
      inner join ' . $schema->str() . '.users u
        on p.user_id = u.id
      left join ' . $schema->str() . '.contact c
        on c.user_id = u.id
          and c.id_type_contact = (select t.id
            from ' . $schema->str() . '.type_contact t
            where t.abbrev = \'mail\')
      where p.id in (:payment_ids)
      group by p.id, u.code, u.name, r.description
      order by p.created_at desc', [
        'payment_ids' => $payment_ids,
      ], [
        'payment_ids' => ArrayParameterType::INTEGER,
      ]);

    while (($row = $res->fetchAssociative()))
    {
      $payments[$row['id']] = [
        ...$row,
        'email_addresses' => json_decode($row['email_addresses']),
      ];
    }

    return $payments;
  }

  public function get_payments_basic_info(
    array $payment_ids,
    Schema $schema,
  ):array
  {
    $payments = [];
    $res = $this->db->executeQuery('select
      p.id, p.user_id, p.amount, r.description
      from ' . $schema->str() . '.mollie_payments p
      inner join ' . $schema->str() . '.mollie_payment_requests r
        on p.request_id = r.id
      where p.id in (:payment_ids)
      order by p.created_at desc', [
        'payment_ids' => $payment_ids,
      ], [
        'payment_ids' => ArrayParameterType::INTEGER,
      ]);

    while ($row = $res->fetchAssociative())
    {
      $payments[$row['id']] = $row;
    }
    return $payments;
  }

  public function add_emails_sent(
    string $sanitized_content,
    string $subject,
    string $route,
    array $payment_ids,
    int $created_by,
    Schema $schema,
  ):void
  {
    $this->db->beginTransaction();
    $this->db->executeStatement('insert into ' .
      $schema->str() . '.emails
      (subject, content, route, created_by, sent_to)
      values
      (:subject, :content, :route, :created_by,
        (select jsonb_agg(p.user_id)
          from ' . $schema->str() . '.mollie_payments p
          where p.id in (:payment_ids)))',[
      'subject'     => $subject,
      'content'     => $sanitized_content,
      'route'       => $route,
      'created_by'  => $created_by,
      'payment_ids' => $payment_ids,
    ], [
      'subject'     => Types::STRING,
      'content'     => Types::STRING,
      'route'       => Types::STRING,
      'created_by'  => Types::INTEGER,
      'payment_ids' => ArrayParameterType::INTEGER,
    ]);

    $email_id = (int) $this->db->lastInsertId($schema->str() . '.emails_id_seq');

		$this->db->executeStatement('update ' .
      $schema->str() . '.mollie_payments
			set emails_sent = coalesce(emails_sent, \'[]\') || :email_id::jsonb
			where id in (:payment_ids)', [
        'email_id'    => $email_id,
        'payment_ids' => $payment_ids,
      ], [
        'email_id' => Types::INTEGER,
        'payment_ids' => ArrayParameterType::INTEGER,
      ]);
    $this->db->commit();
  }

  public function cancel_payments(
    array $payment_ids,
    int $canceled_by,
    Schema $schema,
  ):int
  {
    /**
     * is_canceled and canceled_at are set with
     * postgres function set_canceled_at()
     */
    return $this->db->executeStatement('update ' .
      $schema->str() . '.mollie_payments
      set canceled_by = :canceled_by
      where id in (:payment_ids)
        and not is_paid', [
        'canceled_by' => $canceled_by,
        'payment_ids' => $payment_ids
    ], [
      'canceled_by' => Types::INTEGER,
      'payment_ids' => ArrayParameterType::INTEGER,
    ]);
  }

  public function get_canceled_payments(
    array $payment_ids,
    Schema $schema,
  ):array
  {
    $payments = [];
    $res = $this->db->executeQuery('select
      p.id, p.user_id, p.amount, r.description
      from ' . $schema->str() . '.mollie_payments p
      inner join ' . $schema->str() . '.mollie_payment_requests r
        on p.request_id = r.id
      where p.id in (:payment_ids)
        and p.is_canceled
      order by p.created_at desc', [
        'payment_ids' => $payment_ids,
      ], [
        'payment_ids' => ArrayParameterType::INTEGER,
      ]);

    while ($row = $res->fetchAssociative())
    {
      $payments[$row['id']] = $row;
    }
    return $payments;
  }

  public function get_filtered_payments(
    MollieFilterCommand $filter_command,
    int $start,
    int $limit,
    string $order_by,
    bool $asc,
    Schema $schema,
  ):array
  {
    $allowed_sort_cols = [
      'amount'      => 'p',
      'description' => 'r',
      'code'        => 'u',
      'created_at'  => 'p',
    ];

    if (!isset($allowed_sort_cols[$order_by]))
    {
      throw new \Exception(
        'Not allowed order_by ' . $order_by
      );
    }

    $prefixed_order_by = $allowed_sort_cols[$order_by] . '.' . $order_by;

    $sql = [
      'where'     => [
        'common'  => '1 = 1',
      ],
      'params'    => [],
      'types'     => [],
    ];

    if (isset($filter_command->q))
    {
      $sql['where']['q'] = 'r.description ilike :q';
      $sql['params']['q'] = '%' . $filter_command->q . '%';
      $sql['types']['q'] = Types::STRING;
    }

    if (isset($filter_command->user))
    {
      $sql['where']['user_id'] = 'u.id = :user_id';
      $sql['params']['user_id'] = $filter_command->user;
      $sql['types']['user_id'] = Types::INTEGER;
    }

    if (isset($filter_command->status) && $filter_command->status)
    {
      $st_where_or = [];

      if (in_array('open', $filter_command->status))
      {
        $st_where_or[] = '(not p.is_paid and not p.is_canceled)';
      }

      if (in_array('paid', $filter_command->status))
      {
        $st_where_or[] = 'p.is_paid';
      }

      if (in_array('canceled', $filter_command->status))
      {
        $st_where_or[] = 'p.is_canceled';
      }

      if (count($st_where_or))
      {
        $sql['where']['status'] = '(' . implode(' or ', $st_where_or) . ')';
      }
    }

    if (isset($filter_command->from_date))
    {
      $from_date_immutable = \DateTimeImmutable::createFromFormat('U', (string) strtotime($filter_command->from_date . ' UTC'));
      $sql['where']['from_date'] = 'p.created_at >= :from_date';
      $sql['params']['from_date'] = $from_date_immutable;
      $sql['types']['from_date'] = Types::DATETIME_IMMUTABLE;
    }

    if (isset($filter_command->to_date))
    {
      $to_date_immutable = \DateTimeImmutable::createFromFormat('U', (string) strtotime($filter_command->to_date . ' UTC'));
      $sql['where']['to_date'] = 'p.created_at <= :to_date';
      $sql['params']['to_date'] = $to_date_immutable;
      $sql['types']['to_date'] = Types::DATETIME_IMMUTABLE;
    }

    $sql['params']['limit'] = $limit;
    $sql['types']['limit'] = Types::INTEGER;
    $sql['params']['offset'] = $start;
    $sql['types']['offset'] = Types::INTEGER;

    $sql_where = implode(' and ', $sql['where']);

    $payments = [];

    $res = $this->db->executeQuery('select p.*,
      r.description,
      u.code, u.name,
      u.status,
      u.is_active,
      u.activated_at,
      u.is_leaving,
      coalesce(jsonb_agg(c.value) filter(where c.value is not null), \'[]\') as email_addresses
      from ' . $schema->str() . '.mollie_payments p
      inner join ' . $schema->str() . '.mollie_payment_requests r
        on p.request_id = r.id
      inner join ' . $schema->str() . '.users u
        on p.user_id = u.id
      left join ' . $schema->str() . '.contact c
        on c.user_id = u.id
          and c.id_type_contact = (select t.id
            from ' . $schema->str() . '.type_contact t
            where t.abbrev = \'mail\')
      where ' . $sql_where . '
      group by p.id, r.description,
        u.code, u.name,
        u.status, u.is_active, u.activated_at,
        u.is_leaving
      order by ' . $prefixed_order_by . '
      ' . ($asc ? 'asc' : 'desc') . '
      limit :limit offset :offset',
      $sql['params'], $sql['types']);

    while ($row = $res->fetchAssociative())
    {
      $payments[$row['id']] = [
        ...$row,
        'email_addresses' => json_decode($row['email_addresses'], true),
        'emails_sent' => json_decode($row['emails_sent'], true),
      ];
    }

    $sql_all = $sql;
    unset($sql_all['params']['limit']);
    unset($sql_all['types']['limit']);
    unset($sql_all['params']['offset']);
    unset($sql_all['types']['offset']);
    $sql_where_open = $sql_all['where'];
    $sql_where_open['status'] = 'not p.is_paid and not p.is_canceled';
    $sql_where_open = implode(' and ', $sql_where_open);
    $sql_where_paid = $sql_all['where'];
    $sql_where_paid['status'] = 'p.is_paid';
    $sql_where_paid = implode(' and ', $sql_where_paid);
    $sql_where_canceled = $sql_all['where'];
    $sql_where_canceled['status'] = 'p.is_canceled';
    $sql_where_canceled = implode(' and ', $sql_where_canceled);

    $count_ary = $this->db->fetchAssociative('select
      count(p.*) filter
        (where ' . $sql_where . ') as rows,
      count(p.*) filter
        (where ' . $sql_where_open . ') as open,
      count(p.*) filter
        (where ' . $sql_where_paid . ') as paid,
      count(p.*) filter
        (where ' . $sql_where_canceled . ') as canceled
      from ' . $schema->str() . '.mollie_payments p
      inner join ' . $schema->str() . '.mollie_payment_requests r
        on p.request_id = r.id
      inner join ' . $schema->str() . '.users u
        on p.user_id = u.id',
      $sql_all['params'],
      $sql_all['types']);

    return [
      'payments'  => $payments,
      'count_ary' => $count_ary,
    ];
  }

  public function get_last_status_ary(
    Schema $schema
  ):array
  {
    $ary = [];
    $res = $this->db->executeQuery('select distinct on (u.id)
      u.id, p.is_paid, p.is_canceled,
      p.paid_at, p.canceled_at,
      p.created_at, p.amount, r.description
      from ' . $schema->str() . '.users u
      inner join ' . $schema->str() . '.mollie_payments p
        on u.id = p.user_id
      inner join ' . $schema->str() . '.mollie_payment_requests r
        on r.id = p.request_id
      order by u.id asc, p.created_at desc');

    while (($row = $res->fetchAssociative()))
    {
      $ary[$row['id']] = $row;
    }

    return $ary;
  }
}
