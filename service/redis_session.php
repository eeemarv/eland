<?php

namespace service;

class redis_session implements \SessionHandlerInterface
{
	private $ttl = 172800; // 2 days
	private $redis;

	public function __construct(\Predis\Client $redis)
	{
		$this->redis = $redis;
	}

	public function open($save_path, $session_name)
	{
		return true;
	}

	public function close()
	{
		return true;
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
		return true;
	}

	public function destroy($id)
	{
		$this->redis->del('session_' . $id);
		return true;
	}

	public function gc($max_lifetime)
	{
		return true;
	}
}
