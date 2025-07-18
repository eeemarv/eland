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
		protected Db $db
	)
	{
	}

	public function register(
    Uuid $email_token,
    AddressAry $to_addresses,
    Address $from_address,
    AddressAry $bcc_addresses,
    AddressAry $cc_addresses,
    null|Address $reply_to_address,
    null|Uuid $confirm_token,
    null|array $confirm_data,
    string $template,
    string $subject,
    null|Uuid $bulk_id,
    null|Schema $schema
  ):void
	{
    $insert_ary = [
      'email_token'     => $email_token->toRfc4122(),
      'to_addresses'    => $to_addresses->adr_str_ary(),
      'cc_addresses'    => $cc_addresses->adr_str_ary(),
      'bcc_addresses'   => $bcc_addresses->adr_str_ary(),
      'from_address'    => $from_address->getAddress(),
      'template'        => $template,
      'subject'         => $subject,
    ];

    $type_ary = [
      Types::GUID,
      Types::JSON,
      Types::JSON,
      Types::JSON,
      Types::STRING,
      Types::STRING,
      Types::STRING,
    ];

    if (isset($reply_to_address))
    {
      $insert_ary['reply_to_address'] = $reply_to_address->getAddress();
      $type_ary[] = Types::STRING;
    }

    if (isset($confirm_token))
    {
      $insert_ary['confirm_token'] = $confirm_token->toRfc4122();
      $type_ary[] = Types::GUID;
    }

    if (isset($confirm_data))
    {
      $insert_ary['confirm_data'] = $confirm_data;
      $type_ary[] = Types::JSON;
    }

    if (isset($bulk_id))
    {
      $insert_ary['bulk_id'] = $bulk_id->toRfc4122();
      $type_ary[] = Types::GUID;
    }

    if ($to_addresses->count() === 1
      && $cc_addresses->count() === 0
      && $bcc_addresses->count() === 0
    )
    {
      $insert_ary['single_to_address'] = $to_addresses->adr_str_ary()[0];
      $type_ary[] = Types::STRING;
    }

    $sch_str = isset($schema) ? $schema->str() : 'xdb';

    $this->db->insert($sch_str . '.emails_sent',  $insert_ary, $type_ary);
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
}
