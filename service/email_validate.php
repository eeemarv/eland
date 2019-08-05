<?php declare(strict_types=1);

namespace service;

use service\xdb;
use service\cache;
use service\token;
use Monolog\Logger as monolog;

class email_validate
{
	const CACHE_PREFIX = 'email_validate_token_';
	protected $ttl = 864000; // 10 days
	protected $db;
	protected $xdb;
	protected $cache;
	protected $monolog;

	public function __construct(
		cache $cache,
		xdb $xdb,
		token $token,
		monolog $monolog
	)
	{
		$this->cache = $cache;
		$this->xdb = $xdb;
		$this->token = $token;
		$this->monolog = $monolog;
	}

	public function get_token(string $email, string $schema, string $source):string
	{
		$token = $this->token->gen();

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

		$data = $this->cache->get($cache_key, true);

		if (!count($data))
		{
			return;
		}

		$this->xdb->set('email_validated',
			$data['email'], $data, $data['schema']);

		$this->monolog->debug('email ' . $data['email'] .
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
