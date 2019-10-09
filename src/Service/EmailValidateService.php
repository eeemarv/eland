<?php declare(strict_types=1);

namespace App\Service;

use App\Service\XdbService;
use App\Service\CacheService;
use App\Service\TokenGeneratorService;
use Psr\Log\LoggerInterface;

class EmailValidateService
{
	const CACHE_PREFIX = 'email_validate_token_';
	protected $ttl = 864000; // 10 days
	protected $db;
	protected $xdb_service;
	protected $cache_service;
	protected $logger;
	protected $token_generator_service;

	public function __construct(
		CacheService $cache_service,
		XdbService $xdb_service,
		TokenGeneratorService $token_generator_service,
		loggerinterface $logger
	)
	{
		$this->cache_service = $cache_service;
		$this->xdb_service = $xdb_service;
		$this->token_generator_service = $token_generator_service;
		$this->logger = $logger;
	}

	public function get_token(string $email, string $schema, string $source):string
	{
		$token = $this->token_generator_service->gen();

		$cache_key = self::CACHE_PREFIX . $token;

		$this->cache_service->set($cache_key, [
			'email' 	=> strtolower($email),
			'schema' 	=> $schema,
			'source'	=> $source,
		], $this->ttl);

		return $token;
	}

	public function validate(string $token):void
	{
		$cache_key = self::CACHE_PREFIX . $token;

		$data = $this->cache_service->get($cache_key);

		if (!count($data))
		{
			return;
		}

		$this->xdb_service->set('email_validated',
			$data['email'], $data, $data['schema']);

		$this->logger->debug('email ' . $data['email'] .
			' validated from ' . $data['source'],
			['schema' => $data['schema']]);

		$this->cache_service->del($cache_key);
	}

	public function is_validated(string $email, string $schema):bool
	{
		return $this->xdb_service->get('email_validated',
			$email, $schema) ? true : false;
	}
}
