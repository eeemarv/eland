<?php declare(strict_types=1);

namespace service;

use Predis\Client as Redis;
use service\token as token_gen;

class form_token
{
	protected $redis;
	protected $token_gen;
	protected $token;

	const TTL = 14400; // 4 hours

	public function __construct(Redis $redis, token_gen $token_gen)
	{
		$this->redis = $redis;
		$this->token_gen = $token_gen;
	}

	public function get_posted()
	{
		return $_POST['form_token'];
	}

	public function get():string
	{
		if (!isset($this->token))
		{
			$this->token = $this->token_gen->gen();
			$key = 'form_token_' . $this->token;
			$this->redis->set($key, '1');
			$this->redis->expire($key, self::TTL);
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

		$key = 'form_token_' . $this->get_posted();

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
