<?php declare(strict_types=1);

namespace App\Repository;

use Service\Ev;

class SystemRepository
{
	private $db;
	private $ev;

	private $data = [
		'id'				=> '',
	//	'steward'			=> '',
		'name'				=> '',
		'site_name'			=> '',
		'description'		=> '',
		'mail_tag'			=> '',
		'path_id'			=> '',
		'ratio'				=> '',
		'elas_schema'		=> '',
		'elas_subdomain'	=> '',
		'redirect'			=> '',
		'min_limit'			=> '',
		'max_limit'			=> '',
		'landing_page'		=> 'ad_index',
		'custom'		=> [
			'dddddzzfjeid' => ['account', 'datetime', 'info moment'],
			'sjkdksjlksqj' => ['datetime', 'lidgeld'],
			'klds-doeoopd' => ['int', ''],
		],
	];

	public function __construct(Ev $ev)
	{
		$this->ev = $ev;
	}

	public function set(string $id, array $data)
	{
		$data['segment'] = $id;
		$data['type'] = 'currency';
	
		$config = $this->db->fetchAssoc('select * from ' . $this->schema . '.config');
	}
}
