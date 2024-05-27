<?php declare(strict_types=1);

namespace App\Service;

use Redis;
use App\Service\TokenGeneratorService;

class DataTokenService
{
	public function __construct(
		protected Redis $redis,
		protected TokenGeneratorService $token_generator_service
	)
	{
	}

	private function get_key(string $token, string $name, string|null $schema):string
	{
		$key = 'data_token.' . $token . '.' . $name;

		if (is_string($schema))
		{
			$key .= '.' . $schema;
		}

		return $key;
	}

	public function store(array $data, string $name, string|null $schema, int $ttl):string
	{
		$token = $this->token_generator_service->gen();
		$key = $this->get_key($token, $name, $schema);
		$this->redis->set($key, serialize($data), $ttl);
		return $token;
	}

	public function retrieve(string $token, string $name, string|null $schema):array
	{
		$key = $this->get_key($token, $name, $schema);
		$data = $this->redis->get($key);

		if (!$data)
		{
			return [];
		}

		return unserialize($data);
	}

	public function del(string $token, string $name, string|null $schema):void
	{
		$key = $this->get_key($token, $name, $schema);
		$this->redis->del($key);
	}
}
