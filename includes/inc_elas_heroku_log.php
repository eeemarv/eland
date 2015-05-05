<?php

/** logs in mongodb
 */
 
class elas_heroku_log
{
	private $logs;
	private $schema;

	public function __construct($schema)
	{
		$this->schema = $schema;
	}

	public function set_schema($schema)
	{
		$this->schema = $schema;
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

		$this->connect();
		$this->logs->insert($item);

		return $this;
	}

	public function find($find = array())
	{
		$this->connect();
		return $this->logs->find($find)->sort(array('timestamp' => -1))->limit(200);
	}

	private function connect()
	{
		if (is_object($this->logs))
		{
			return $this;
		}

		$url = getenv('MONGOLAB_URI');
		$mongo_client = new MongoClient($url);
		$path = parse_url($url, PHP_URL_PATH);
		$logdb = $mongo_client->selectDB(trim($path, '/'));
		$collection_name = $this->schema . '_logs';
		$this->logs = $logdb->$collection_name;

		return $this;
	}
}
