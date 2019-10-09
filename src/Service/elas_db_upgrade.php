<?php declare(strict_types=1);
namespace App\Service;

use Doctrine\DBAL\Connection as Db;

class elas_db_upgrade
{
	public function __construct(Db $db)
	{
		$this->db = $db;
	}

	public function run(int $version, string $schema):void
	{
		$this->db->beginTransaction();

		switch($version)
		{
			case 30000:
				break;

			case 30001:
				$query = 'alter table ' . $schema . '.transactions
					alter column transid type character varying(200)';
				exec($query);
				break;

			case 31000:
				$this->db->delete($schema . '.letsgroups', ['id' => 0]);
				break;

			case 31002:
				$query = "insert into ' . $schema . '.config
					(category,setting,value,description,default)
					values('system','ets_enabled','0', '', 0)";

				$this->db->insert($schema . '.config', [
					'category' 		=> 'system',
					'setting'		=> 'ets_enabled',
					'value'			=> '0',
					'description'	=> 'Enable ETS functionality',
					'default'		=> 0]);
				break;

			case 31003:
				// FIXME: We need to repeat 2205 and 2206 to fix imported transactions after those updates
				break;
			default:

				break;

		}
		$this->db->update($schema . '.parameters',
			['value' => $version],
			['parameter' => 'schemaversion']);
		$this->db->commit();
	}
}
