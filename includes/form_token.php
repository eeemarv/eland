<?php

namespace eland;

use Predis\Client as Redis;
use Monolog\Logger;

class form_token
{
	public $ttl = 14400; // 4 hours
	private $redis;
	private $token;
	private $monolog;
	private $script_name;

	public function __construct(Redis $redis, Logger $monolog, string $script_name)
	{
		$this->redis = $redis;
		$this->monolog = $monolog;
		$this->script_name = $script_name;
	}

	public function generate($print = true)
	{
		if (!isset($this->token))
		{
			$this->token = sha1(microtime() . mt_rand(0, 1000000));
			$key = 'form_token_' . $this->token;
			$this->redis->set($key, '1');
			$this->redis->expire($key, $this->ttl);
		}

		if ($print)
		{
			echo '<input type="hidden" name="form_token" value="' . $this->token . '">';
		}

		return $this->token;
	}

	/**
	*
	*/

	public function get_error()
	{
		if (!isset($_POST['form_token']))
		{
			return 'Het formulier bevat geen token';
		}

		$token = $_POST['form_token'];
		$key = 'form_token_' . $token;

		$value = $this->redis->get($key);

		if (!$value)
		{
			$m = 'Het formulier is verlopen';
			$this->monolog->debug('form_token: ' . $m . ': ' . $this->script_name);
			return $m;
		}

		if ($value > 1)
		{
			$this->redis->incr($key);
			$m = 'Een dubbele ingave van het formulier werd voorkomen.';
			$this->monolog->debug('form_token: ' . $m . '(count: ' . $value . ') : ' . $this->script_name);
			return $m;
		}

		$this->redis->incr($key);

		return false;
	}
}
