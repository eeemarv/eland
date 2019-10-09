<?php declare(strict_types=1);

namespace App\Service;

use Predis\Client as Predis;
use App\Service\TokenGenerator;

class DataToken
{
	protected $predis;
	protected $token_generator;
	protected $token;

	const KEY = 'data_token_%token%_%name%_%schema%';

	public function __construct(Predis $predis, TokenGenerator $token_generator)
	{
		$this->predis = $predis;
		$this->token_generator = $token_generator;
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
		$token = $this->token_generator->gen();
		$key = $this->get_redis_key($token, $name, $schema);
		$this->predis->set($key, serialize($data));
		$this->predis->expire($key, $ttl);

		return $token;
	}

	public function retrieve(string $token, string $name, string $schema):array
	{
		$key = $this->get_redis_key($token, $name, $schema);
		$data = $this->predis->get($key);

		if (!$data)
		{
			return [];
		}

		return unserialize($data);
	}

	public function del(string $token, string $name, string $schema):void
	{
		$key = $this->get_redis_key($token, $name, $schema);
		$this->predis->del($key);
	}
}
