<?php
namespace service;

use Doctrine\DBAL\Connection as db;

class elas_db_upgrade
{
	public function __construct(db $db)
	{
		$this->db = $db;
	}

	public function run($version)
	{
		global $app;

		$this->db->beginTransaction();

		try{

			switch($version)
			{
				case 30000:
					break;

				case 30001:
					$query = 'alter table transactions
						alter column transid type character varying(200)';
					exec($query);
					break;

				case 31000:
					$this->db->delete('letsgroups', ['id' => 0]);
					break;

				case 31002:
					$query = "insert into config
						(category,setting,value,description,default)
						values('system','ets_enabled','0', '', 0)";

					$this->db->insert('config', [
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
			$this->db->update('parameters',
				['value' => $version],
				['parameter' => 'schemaversion']);
			$this->db->commit();
			return true;
		}
		catch(Exception $e)
		{
			$this->db->rollback();
			throw $e;
			return false;

		}
	}
}
