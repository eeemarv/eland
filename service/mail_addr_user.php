<?php

namespace service;

use Doctrine\DBAL\Connection as db;
use Monolog\Logger;

class mail_addr_user
{
	protected $db;
	protected $monolog;

	public function __construct(db $db, Logger $monolog)
	{
		$this->db = $db;
		$this->monolog = $monolog;
	}

	public function get(int $user_id, string $schema, bool $active_only = true):array
	{
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
			$name = $row['letscode'] . ' ' . $row['name'];

			if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
			{
				$this->monolog->error('Mail Addr User: invalid address : ' .
					$mail . ' for user: ' . $name,
					['schema' => $schema]);
				continue;
			}

			$out[$mail] = $name;
		}

		return $out;
	}
}
