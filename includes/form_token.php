<?php

namespace eland;

class form_token
{
	public $ttl = 14400; // 4 hours
	private $redis;
	private $token;

	public function __construct(\Predis\Client $redis)
	{
		$this->redis = $redis;
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
		global $script_name;

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
			log_event('form_token', $m . ': ' . $script_name);
			return $m;
		}

		if ($value > 1)
		{
			$this->redis->incr($key);
			$m = 'Een dubbele ingave van het formulier werd voorkomen.';
			log_event('form_token', $m . '(count: ' . $value . ') : ' . $script_name);
			return $m;
		}

		$this->redis->incr($key);

		return false;
	}
}
