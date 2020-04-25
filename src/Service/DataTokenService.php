<?php declare(strict_types=1);

namespace App\Service;

use Predis\Client as Predis;
use App\Service\TokenGeneratorService;

class DataTokenService
{
	protected Predis $predis;
	protected TokenGeneratorService $token_generator_service;

	const KEY = 'data_token_%token%_%name%_%schema%';

	public function __construct(
		Predis $predis,
		TokenGeneratorService $token_generator_service
	)
	{
		$this->predis = $predis;
		$this->token_generator_service = $token_generator_service;
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
		$token = $this->token_generator_service->gen();
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
