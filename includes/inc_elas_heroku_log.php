<?php
 
class elas_heroku_log
{
	private $insert_items;
	private $elas_mongo;

	public function __construct(elas_mongo $elas_mongo)
	{
		$this->insert_items = array();
		$this->elas_mongo = $elas_mongo;
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

		$this->insert_items[] = $item;

		return $this;
	}

	public function flush()
	{
		if (!count($this->insert_items))
		{
			return;
		}

		$this->elas_mongo->connect();
		
		foreach ($this->insert_items as $item)
		{
			$this->elas_mongo->logs->insert($item);
		}

		$this->insert_items = array();

		return $this;
	}

	public function find($find = array())
	{
		$this->elas_mongo->connect();
		return $this->elas_mongo->logs->find($find)->sort(array('timestamp' => -1))->limit(200);
	}

	/* cleanup logs older than 30 days. */
	public function cleanup()
	{
		$this->elas_mongo->connect();
		
		$treshold = gmdate('Y-m-d H:i:s', time() - 86400 * 30);
		$this->elas_mongo->logs->remove(array('timestamp' => array('$lt' => $treshold)));
		return $this;
	}
}
