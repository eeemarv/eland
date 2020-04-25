<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class MailAddrUserService
{
	protected Db $db;
	protected LoggerInterface $logger;

	public function __construct(Db $db, LoggerInterface $logger)
	{
		$this->db = $db;
		$this->logger = $logger;
	}

	public function get(int $user_id, string $schema):array
	{
		return $this->get_ary($user_id, $schema, false);
	}

	public function get_active(int $user_id, string $schema):array
	{
		return $this->get_ary($user_id, $schema, true);
	}

	private function get_ary(int $user_id, string $schema, bool $active_only):array
	{
		if (!$user_id)
		{
			return [];
		}

		$out = [];

		$status_sql = $active_only ? ' and u.status in (1,2)' : '';

		$st = $this->db->prepare('select c.value, u.name, u.letscode
			from ' . $schema . '.contact c,
				' . $schema . '.type_contact tc,
				' . $schema . '.users u
			where c.id_type_contact = tc.id
				and c.id_user = ?
				and c.id_user = u.id
				and tc.abbrev = \'mail\''
				. $status_sql);

			$st->bindValue(1, $user_id);
			$st->execute();

		while ($row = $st->fetch())
		{
			$mail = trim($row['value']);

			$code = $row['letscode'] ?? '';
			$name = $row['name'] ?? '';

			$code_name = $code . ' ' . $name;

			if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
			{
				$this->logger->error('Mail Addr User: invalid address : ' .
					$mail . ' for user: ' . $code_name,
					['schema' => $schema]);
				continue;
			}

			$out[$mail] = $code_name;
		}

		return $out;
	}
}
