<?php

/** logs in mongodb
 */
 
class elas_heroku_log
{
	private $logdb;
	private $logs;

	function __construct($schema)
	{
		$url = getenv('MONGOLAB_URI');
		$mongo_client = new MongoClient($url);
		$path = parse_url($url, PHP_URL_PATH);
		$this->logdb = $mongo_client->selectDB(trim($path, '/'));
		$collection_name = $schema . '_logs';
		$this->logs = $this->logdb->$collection_name;
	}

	public function insert($id, $type, $event)
	{
		if ($id)
		{
			$user = readuser($id);
			$username = $user['name'];
			$letscode = $user['letscode'];
		}
		else
		{
			$username = $letscode = '';
		}

		$item = array(
			'ts_tz'		=> date('Y-m-d H:i:s'),
			'timestamp'	=> gmdate('Y-m-d H:i:s'),
			'user_id' 	=> $id,
			'letscode'	=> strtolower($letscode),
			'username'	=> $username,
			'ip'		=> $_SERVER['REMOTE_ADDR'],
			'type'		=> strtolower($type),
			'event'		=> $event,
		);

		$result = $this->logs->insert($item);
		
		return $this;
	}

	public function find($find = array())
	{
		return $this->logs->find($find)->sort(array('timestamp' => -1))->limit(200);
	}
}
