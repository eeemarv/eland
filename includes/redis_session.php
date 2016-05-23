<?php

class redis_session implements SessionHandlerInterface
{
	public $ttl = 172800; // 2 days
	protected $redis;

	public function __construct(\Predis\Client $redis)
	{
		$this->redis = $redis;
	}

	public function open($save_path, $session_name)
	{
		// No action necessary because connection is injected
		// in constructor and arguments are not applicable.
	}

	public function close()
	{
		$this->redis = null;
		unset($this->redis);
	}

	public function read($id)
	{
		$id = 'session_' . $id;
		$session_data = $this->redis->get($id);
		$this->redis->expire($id, $this->ttl);
		return $session_data;
	}

	public function write($id, $session_data)
	{
		$id = 'session_' . $id;
		$this->redis->set($id, $session_data);
		$this->redis->expire($id, $this->ttl);
	}

	public function destroy($id)
	{
		$this->redis->del('session_' . $id);
	}

	public function gc($max_lifetime)
	{
		// no action necessary because using EXPIRE
	}
}
