<?php declare(strict_types=1);

namespace service;

use Predis\Client as Redis;
use service\token as token_gen;

class data_token
{
	protected $redis;
	protected $token_gen;
	protected $token;

	const KEY = 'data_token_%token%_%name%_%schema%';

	public function __construct(Redis $redis, token_gen $token_gen)
	{
		$this->redis = $redis;
		$this->token_gen = $token_gen;
	}

	private function get_redis_key(string $token, string $name, string $schema):string
	{
		return strtr(self::KEY, [
			'%token%'	=> $token,
			'%name%'	=> $name,
			'%schema%'	=> $schema,
		]);
	}

	public function store(array $data, string $name, string $schema, int $ttl):string
	{
		$token = $this->token_gen->gen();
		$key = $this->get_redis_key($token, $name, $schema);
		$this->redis->set($key, serialize($data));
		$this->redis->expire($key, $ttl);

		return $token;
	}

	public function retrieve(string $token, string $name, string $schema):array
	{
		$key = $this->get_redis_key($token, $name, $schema);
		$data = $this->redis->get($key);

		if (!$data)
		{
			return [];
		}

		return unserialize($data);
	}

	public function del(string $token, string $name, string $schema):void
	{
		$key = $this->get_redis_key($token, $name, $schema);
		$this->redis->del($key);
	}
}
