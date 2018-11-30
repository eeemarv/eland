<?php

namespace service;

use Predis\Client as Redis;

class form_token
{
	protected $ttl = 14400; // 4 hours
	protected $redis;
	protected $token;
	protected $script_name;

	public function __construct(Redis $redis, string $script_name)
	{
		$this->redis = $redis;
		$this->script_name = $script_name;
	}

	public function get():string
	{
		if (!isset($this->token))
		{
			$this->token = sha1(microtime() . mt_rand(0, 1000000));
			$key = 'form_token_' . $this->token;
			$this->redis->set($key, '1');
			$this->redis->expire($key, $this->ttl);
		}

		return $this->token;
	}

	public function get_hidden_input():string
	{
		return '<input type="hidden" name="form_token" value="' . $this->get() . '">';
	}

	public function get_error()
	{
		if (!isset($_POST['form_token']))
		{
			return 'Het formulier bevat geen form token';
		}

		$token = $_POST['form_token'];
		$key = 'form_token_' . $token;

		$value = $this->redis->get($key);

		if (!$value)
		{
			$m = 'Het formulier is verlopen';
			return $m;
		}

		if ($value > 1)
		{
			$this->redis->incr($key);
			$m = 'Een dubbele ingave van het formulier werd voorkomen.';
			return $m;
		}

		$this->redis->incr($key);

		return false;
	}
}
