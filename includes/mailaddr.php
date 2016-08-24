<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Monolog\Logger;

use eland\this_group;

class mailaddr
{
	private $db;
	private $monolog;
	private $script_name;
	private $schema;

	public function __construct(db $db, Logger $monolog, this_group $this_group, string $script_name)
	{
		$this->db = $db;
		$this->monolog = $monolog;
		$this->this_group = $this_group;
		$this->script_name = $script_name;
	}

	/*
	 * param string mail addr | [string.]int [schema.]user id | array
	 * param string sending_schema
	 * return array
	 */

	function get($m, string $sending_schema = '')
	{
		global $s_admin;

		$sch = ($sending_schema) ?: $this->this_group->get_schema();

		if (!is_array($m))
		{
			$m = explode(',', $m);
		}

		$out = [];

		foreach ($m as $in)
		{
			$in = trim($in);

			$remote_id = strrchr($in, '.');
			$remote_schema = str_replace($remote_id, '', $in);
			$remote_id = trim($remote_id, '.');

			if (in_array($in, ['admin', 'newsadmin', 'support']))
			{
				$ary = explode(',', readconfigfromdb($in));

				foreach ($ary as $mail)
				{
					$mail = trim($mail);

					if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
					{
						$this->monolog->error('mail error: invalid ' . $in . ' mail address : ' . $mail);
						continue;
					}

					$out[$mail] = readconfigfromdb('systemname');
				}
			}
			else if (in_array($in, ['from', 'noreply']))
			{
				$mail = getenv('MAIL_' . strtoupper($in) . '_ADDRESS');
				$mail = trim($mail);

				if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
				{
					$this->monolog->error('mail error: invalid ' . $in . ' mail address : ' . $mail);

					continue;
				}

				$out[$mail] = readconfigfromdb('systemname', $sch);
			}
			else if (ctype_digit((string) $in))
			{
				$status_sql = ($s_admin) ? '' : ' and u.status in (1,2)';

				$st = $this->db->prepare('select c.value, u.name, u.letscode
					from contact c,
						type_contact tc,
						users u
					where c.id_type_contact = tc.id
						and c.id_user = ?
						and c.id_user = u.id
						and tc.abbrev = \'mail\''
						. $status_sql);

				$st->bindValue(1, $in);
				$st->execute();

				while ($row = $st->fetch())
				{
					$mail = trim($row['value']);

					if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
					{
						$this->monolog->error('mail error: invalid mail address : ' . $mail . ', user id: ' . $in);
						continue;
					}

					$out[$mail] = $row['letscode'] . ' ' . $row['name'];
				}
			}
			else if (ctype_digit((string) $remote_id) && $remote_schema)
			{
				$st = $this->db->prepare('select c.value, u.name, u.letscode
					from ' . $remote_schema . '.contact c,
						' . $remote_schema . '.type_contact tc,
						' . $remote_schema . '.users u
					where c.id_type_contact = tc.id
						and c.id_user = ?
						and c.id_user = u.id
						and u.status in (1, 2)
						and tc.abbrev = \'mail\'');

				$st->bindValue(1, $remote_id);
				$st->execute();

				while ($row = $st->fetch())
				{
					$mail = trim($row['value']);
					$letscode = trim($row['letscode']);
					$name = trim($row['name']);

					$user = $remote_schema . '.' . $letscode . ' ' . $name;

					if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
					{
						$this->monolog->error('mail error: invalid mail address from interlets: ' . $mail . ', user: ' . $user);
						continue;
					}

					$out[$mail] = $user;
				}
			}
			else if (filter_var($in, FILTER_VALIDATE_EMAIL))
			{
				$out[] = $in;
			}
			else
			{
				$this->monolog->error('mail error: no valid input for mail adr: ' . $in);
			}
		}

		if (!count($out))
		{
			$this->monolog->error('mail error: no valid mail adress found for: ' . implode('|', $m));
			return $out;
		} 

		return $out;
	}
}
