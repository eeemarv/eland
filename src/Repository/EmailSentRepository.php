<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\AddressAry;
use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Mime\Address;
use Symfony\Component\Uid\Uuid;

class EmailSentRepository
{
	public function __construct(
		private readonly Db $db
	)
	{
	}

	public function register(
    Uuid $email_token,
    AddressAry $to_addresses,
    Address $from_address,
    AddressAry $bcc_addresses,
    AddressAry $cc_addresses,
    AddressAry $reply_to_addresses,
    null|Uuid $confirm_token,
    null|array $confirm_data,
    string $template,
    string $subject,
    string $message_class,
    null|Uuid $bulk_id,
    null|int $mollie_payment_id,
    null|Schema $schema
  ):void
	{
    /* use returning id
    $insert_ary = [
      'email_token'     => $email_token->toRfc4122(),
      'to_addresses'    => $to_addresses->adr_str_ary(),
      'cc_addresses'    => $cc_addresses->adr_str_ary(),
      'bcc_addresses'   => $bcc_addresses->adr_str_ary(),
      'reply_to_addresses'  => $reply_to_addresses->adr_str_ary(),
      'from_address'    => $from_address->getAddress(),
      'template'        => $template,
      'subject'         => $subject,
      'message_class'   => $message_class,
    ];

    $type_ary = [
      Types::GUID,
      Types::JSONB,
      Types::JSONB,
      Types::JSONB,
      Types::JSONB,
      Types::STRING,
      Types::STRING,
      Types::STRING,
      Types::STRING,
    ];

    if (isset($confirm_token))
    {
      $insert_ary['confirm_token'] = $confirm_token->toRfc4122();
      $type_ary[] = Types::GUID;
    }

    if (isset($confirm_data))
    {
      $insert_ary['confirm_data'] = $confirm_data;
      $type_ary[] = Types::JSONB;
    }

    if (isset($bulk_id))
    {
      $insert_ary['bulk_id'] = $bulk_id->toRfc4122();
      $type_ary[] = Types::GUID;
    }
    */

    $single_to_address = null;

    if ($to_addresses->count() === 1
      && $cc_addresses->count() === 0
      && $bcc_addresses->count() === 0
    )
    {
      $single_to_address = $to_addresses->adr_str_ary()[0];
    }

    $sch_str = isset($schema) ? $schema->str() : 'xdb';

    /*
    $this->db->insert($sch_str . '.emails_sent',  $insert_ary, $type_ary);
    */

    $stmt = $this->db->prepare('insert into ' . $sch_str . '.emails_sent
      (email_token, to_addresses, cc_addresses, bcc_addresses,
      reply_to_addresses, from_address, template, subject,
      message_class, confirm_token, confirm_data, bulk_id,
      single_to_address)
      values (:email_token, :to_addresses, :cc_addresses, :bcc_addresses,
      :reply_to_addresses, :from_address, :template, :subject,
      :message_class, :confirm_token, :confirm_data, :bulk_id,
      :single_to_address) returning id');
    $stmt->bindValue('email_token', $email_token->toRfc4122(), Types::GUID);
    $stmt->bindValue('to_addresses', $to_addresses->adr_str_ary(), Types::JSONB);
    $stmt->bindValue('cc_addresses', $cc_addresses->adr_str_ary(), Types::JSONB);
    $stmt->bindValue('bcc_addresses', $bcc_addresses->adr_str_ary(), Types::JSONB);
    $stmt->bindValue('reply_to_addresses', $reply_to_addresses->adr_str_ary(), Types::JSONB);
    $stmt->bindValue('from_address', $from_address->getAddress(), Types::STRING);
    $stmt->bindValue('template', $template, Types::STRING);
    $stmt->bindValue('subject', $subject, Types::STRING);
    $stmt->bindValue('message_class', $message_class, Types::STRING);
    $stmt->bindValue('confirm_token', $confirm_token?->toRfc4122(), Types::GUID);
    $stmt->bindValue('confirm_data', $confirm_data, Types::JSONB);
    $stmt->bindValue('bulk_id', $bulk_id?->toRfc4122(), Types::GUID);
    $stmt->bindValue('single_to_address', $single_to_address, Types::STRING);
    $res = $stmt->executeQuery();
    $email_sent_id = (int) $res->fetchOne();

    if (isset($mollie_payment_id))
    {
      if (!isset($schema))
      {
        throw new \Exception('missing schema for registering mollie_emails_sent');
      }

      $this->db->insert($schema->str() . '.mollie_emails_sent', [
        'payment_id'    => $mollie_payment_id,
        'email_sent_id' => $email_sent_id,
      ], [
        Types::INTEGER,
        Types::INTEGER
      ]);
    }
	}

  public function get_with_confirm_token (
    Uuid $confirm_token,
    int $minutes_exp,
    Schema|null $schema
  ):array|false
  {
    $sch_str = isset($schema) ? $schema->str() : 'xdb';

		$stmt = $this->db->prepare('select confirm_data,
      single_to_address, confirmed_at,
      message_class,
      (confirmed_at is not null) as is_confirmed,
      (timezone(\'utc\', now()) - created_at > interval \'' . $minutes_exp . ' minutes\') as is_expired
			from ' . $sch_str . '.emails_sent
			where confirm_token = :confirm_token');
		$stmt->bindValue('confirm_token', $confirm_token->toRfc4122(), Types::GUID);
		$res = $stmt->executeQuery();
		$row = $res->fetchAssociative();

    if ($row === false){
      return false;
    }

    $row['confirm_data'] = json_decode($row['confirm_data'], true);

    return $row;
  }

  public function set_confirmed (
    Uuid $confirm_token,
    Schema|null $schema
  ):int
  {
    $sch_str = isset($schema) ? $schema->str() : 'xdb';

    $stmt = $this->db->prepare('update ' . $sch_str . '.emails_sent
      set confirmed_at = timezone(\'utc\', now())
      where confirm_token = :confirm_token');
    $stmt->bindValue('confirm_token', $confirm_token->toRfc4122(), Types::GUID);
    return $stmt->executeStatement();
  }

  public function register_on_email_token (
    Uuid $email_token,
    string $path_info,
    Schema|null $schema
  ):void
  {
    $sch_str = isset($schema) ? $schema->str() : 'xdb';
    $uuid_et = $email_token->toRfc4122();

    $stmt_1 = $this->db->prepare('update ' . $sch_str . '.emails_sent
      set last_verified_at = timezone(\'utc\', now())
      where email_token = :email_token');
    $stmt_1->bindValue('email_token', $uuid_et, Types::GUID);
    $stmt_1->executeStatement();

    $stmt_2 =  $this->db->prepare('insert into ' . $sch_str . '.emails_sent_verified
      (email_sent_id, path_info)
      select id, :path_info
      from ' . $sch_str . '.emails_sent
      where email_token = :email_token');
    $stmt_2->bindValue('path_info', $path_info, Types::STRING);
    $stmt_2->bindValue('email_token', $uuid_et, Types::GUID);
    $stmt_2->executeStatement();
  }

	public function insert_bulk_if_not_exists(
    Uuid $bulk_id,
    string $subject,
    string $content,
    int $created_by,
    Schema $schema,
  ):void
	{
		$stmt = $this->db->prepare('insert into ' .
      $schema->str() . '.emails_sent_bulk(id, subject, content, created_by)
      values(:id, :subject, :content, :created_by)
      on conflict do nothing');
		$stmt->bindValue('id', $bulk_id->toRfc4122(), Types::GUID);
		$stmt->bindValue('subject', $subject, Types::STRING);
		$stmt->bindValue('content', $content, Types::STRING);
		$stmt->bindValue('created_by', $created_by, Types::INTEGER);
		$stmt->executeStatement();
	}
}
