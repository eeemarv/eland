<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Generate an base64 encoded encryption key and publish it as
 * environment variable
 * php cli (enter with php -a):
 * echo base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
 * APP_SECRETS_ENCRYPTION_KEY="your_generated_encryption_key"
 */

class SecretRepository
{
	public function __construct(
		private readonly Db $db,
    #[\SensitiveParameter]
    #[Autowire(env:'APP_SECRETS_ENCRYPTION_KEY')]
    private readonly string $env_app_secrets_encryption_key,
	)
	{
	}

	public function add(
    string $name,
		#[\SensitiveParameter] string $value,
    string|null $comment,
    int $created_by,
		Schema $schema,
	):int
	{
    $key = base64_decode($this->env_app_secrets_encryption_key);

    $nonce = random_bytes(
      SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
    );

    $cipher_text = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
      $value,
      '',
      $nonce,
      $key,
    );

    $encrypted_value = base64_encode($nonce . $cipher_text);

    $insert_ary = [
      'encrypted_value'  => $encrypted_value,
      'name' => $name,
      'comment' => $comment,
      'created_by'  => $created_by,
    ];

    $type_ary = [
      'encrypted_value'  => Types::STRING,
      'name' => Types::STRING,
      'comment' => Types::STRING,
      'created_by'  => Types::INTEGER,
    ];

		$affected_rows = (int) $this->db->insert(
      table: $schema->str() . '.secrets',
      data: $insert_ary,
      types: $type_ary,
    );

    return $affected_rows;
	}

	public function get_current_value(
    string $name,
		Schema $schema,
	):string|null
	{
    $encrypted_value = $this->db->fetchOne(
      'select encrypted_value from ' . $schema->str() . '.secrets ' .
      'where name = :name ' .
      'order by id desc limit 1',
      [
        'name' => $name,
      ],
      [
        'name' => Types::STRING,
      ],
    );

    if ($encrypted_value === false)
    {
      return null;
    }

    $decoded_encrypted_value = base64_decode($encrypted_value);

    $key = base64_decode($this->env_app_secrets_encryption_key);
    $nonce_length = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

    $nonce = substr($decoded_encrypted_value, 0, $nonce_length);
    $cipher_text = substr($decoded_encrypted_value, $nonce_length);

    $decrypted_value = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
      $cipher_text,
      '',
      $nonce,
      $key,
    );

    return $decrypted_value;
	}

  public function get_current_ary(
    array $name_ary,
    Schema $schema,
  ):array
  {
    $ret = [];

    $res = $this->db->executeQuery('select distinct on (s.name)
      s.name,
      s.id, s.comment,
      s.created_at, s.created_by, u.name as username,
      u.code as user_code
      from ' . $schema->str() . '.secrets s
      left join ' . $schema->str() . '.users u
        on s.created_by = u.id
      where s.name in (:name_ary)
      order by s.name asc, s.created_at desc', [
        'name_ary' => $name_ary,
      ], [
        'name_ary' => ArrayParameterType::STRING,
      ]);

    while ($row = $res->fetchAssociative())
    {
      $ret[$row['name']] = $row;
    }

    return $ret;
  }

  public function get_current(
    string $name,
    Schema $schema,
  ):array|false
  {
    $ret = $this->get_current_ary(
      [$name],
      $schema,
    );
    if (count($ret) === 0)
    {
      return false;
    }
    return $ret[$name];
  }

  public function have_values(
    array $name_ary,
    Schema $schema,
  ):array
  {
    $ret = [];

    $res = $this->db->executeQuery('select name
      from ' . $schema->str() . '.secrets
      where name in (:name_ary)', [
        'name_ary' => $name_ary,
      ], [
        'name_ary' => ArrayParameterType::STRING,
      ]);

    while ($row = $res->fetchAssociative())
    {
      $ret[$row['name']] = true;
    }

    return $ret;
  }

  public function has_value(
    string $name,
    Schema $schema,
  ):bool
  {
    $id = $this->db->fetchOne('select id
      from ' . $schema->str() . '.secrets
      where name = :name', [
        'name' => $name,
      ], [
        'name' => Types::STRING,
      ]);

    return $id !== false;
  }

  public function get_history(
    array $name_ary,
    Schema $schema,
  ):array
  {
    $res = $this->db->executeQuery('select s.id,
      s.name, s.comment,
      s.created_at, s.created_by, u.name as username,
      u.code as user_code
      from ' . $schema->str() . '.secrets s
      left join ' . $schema->str() . '.users u
        on u.id = s.created_by
      where name in (:name_ary)
      order by s.created_at desc', [
        'name_ary' => $name_ary,
      ], [
        'name_ary' => ArrayParameterType::STRING,
      ]);

    $ret = [];

    while ($row = $res->fetchAssociative())
    {
      $ret[$row['id']] = $row;
    }

    return $ret;
  }

  public function get_mollie_config_history(
    Schema $schema,
  ):array
  {
    $ret = [];
    $res = $this->db->executeQuery('select
      s.name, s.comment,
      s.created_at, s.created_by,
      u1.name as username,
      u1.code as user_code,
      null as old_data,
      null as new_data
      from ' . $schema->str() . '.secrets s
      left join ' . $schema->str() . '.users u1
        on u1.id = s.created_by
      where s.name in (\'mollie_test_api_key\',
        \'mollie_live_api_key\',
        \'mollie_webhook_key\')
      union all
      select cl.config_id as name, cl.comment,
        cl.created_at, cl.created_by,
        u2.name as username,
        u2.code as user_code,
        cl.old_data, cl.new_data
      from ' . $schema->str() . '.config_logs cl
      left join ' . $schema->str() . '.users u2
        on u2.id = cl.created_by
      where cl.config_id in (
        \'mollie.enabled\',
        \'mollie.mode\')
      order by created_at desc');

    while ($row = $res->fetchAssociative())
    {
      $ret[] = [...$row,
        'old_data' => is_string($row['old_data']) ?
          json_decode($row['old_data']) : null,
        'new_data' => is_string($row['new_data']) ?
          json_decode($row['new_data']) : null,
      ];
    }

    return $ret;
  }
}
