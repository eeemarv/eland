<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use App\Service\CacheService;
use App\Service\TokenGeneratorService;
use Psr\Log\LoggerInterface;

class EmailVerifyService
{
	const CACHE_PREFIX = 'email_verify_token_';
	const TTL = 864000; // 10 days

	public function __construct(
		protected CacheService $cache_service,
		protected Db $db,
		protected TokenGeneratorService $token_generator_service,
		protected loggerinterface $logger
	)
	{
	}

	public function get_token(string $email, string $schema, string $source):string
	{
		$token = $this->token_generator_service->gen();

		$cache_key = self::CACHE_PREFIX . $token;

		$this->cache_service->set($cache_key, [
			'email' 	=> strtolower($email),
			'schema' 	=> $schema,
			'source'	=> $source,
		], self::TTL);

		return $token;
	}

	public function verify(string $token):void
	{
		$cache_key = self::CACHE_PREFIX . $token;

		$data = $this->cache_service->get($cache_key);

		if (!count($data))
		{
			return;
		}

		$this->db->insert($data['schema'] . '.email_verify', [
			'email'		=> $data['email'],
			'source'	=> $data['source'],
			'token'		=> $token,
		]);

		$this->logger->debug('email ' . $data['email'] .
			' verfied from ' . $data['source'],
			['schema' => $data['schema']]);

		$this->cache_service->del($cache_key);
	}

	public function is_verified(string $email, string $schema):bool
	{
		return $this->db->fetchOne('select id
			form ' . $schema . '.email_verify
			where email = ?', [$email], [\PDO::PARAM_STR]) ? true : false;
	}
}
