<?php declare(strict_types=1);

namespace App\Service;

use App\Service\Xdb;
use App\Service\Cache;
use App\Service\TokenGenerator;
use Psr\Log\LoggerInterface;

class EmailValidate
{
	const CACHE_PREFIX = 'email_validate_token_';
	protected $ttl = 864000; // 10 days
	protected $db;
	protected $xdb;
	protected $cache;
	protected $logger;
	protected $token_generator;

	public function __construct(
		Cache $cache,
		Xdb $xdb,
		TokenGenerator $token_generator,
		loggerinterface $logger
	)
	{
		$this->cache = $cache;
		$this->xdb = $xdb;
		$this->token_generator = $token_generator;
		$this->logger = $logger;
	}

	public function get_token(string $email, string $schema, string $source):string
	{
		$token = $this->token_generator->gen();

		$cache_key = self::CACHE_PREFIX . $token;

		$this->cache->set($cache_key, [
			'email' 	=> strtolower($email),
			'schema' 	=> $schema,
			'source'	=> $source,
		], $this->ttl);

		return $token;
	}

	public function validate(string $token):void
	{
		$cache_key = self::CACHE_PREFIX . $token;

		$data = $this->cache->get($cache_key);

		if (!count($data))
		{
			return;
		}

		$this->xdb->set('email_validated',
			$data['email'], $data, $data['schema']);

		$this->logger->debug('email ' . $data['email'] .
			' validated from ' . $data['source'],
			['schema' => $data['schema']]);

		$this->cache->del($cache_key);
	}

	public function is_validated(string $email, string $schema):bool
	{
		return $this->xdb->get('email_validated',
			$email, $schema) ? true : false;
	}
}