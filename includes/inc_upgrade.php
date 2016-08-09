<?php
# Perform a DB update

function doupgrade($version)
{
	global $app;
	global $configuration;

	$app['db']->beginTransaction();

	try{

		switch($version)
		{
			case 30000:
				break;

			case 30001:
				$query = "ALTER TABLE transactions ALTER COLUMN transid TYPE character varying(200)";
				exec($query);
				break;

			case 31000:
				$app['db']->delete('letsgroups', ['id' => 0]);
				break;

			case 31002:
				$query = "INSERT INTO config (category,setting,value,description,default) VALUES('system','ets_enabled','0', '', 0)";
				$app['db']->insert('config', [
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
		$app['db']->update('parameters', ['value' => $version], ['parameter' => 'schemaversion']);
		$app['db']->commit();
		return true;
	}
	catch(Exception $e)
	{
		$app['db']->rollback();
		throw $e;
		return false;

	}
}
