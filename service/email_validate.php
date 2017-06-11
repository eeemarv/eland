<?php

namespace service;

use service\xdb;
use service\cache;
use service\token;
use Monolog\Logger as monolog;

class email_validate
{
	public $ttl = 345600; // 4 days
	private $db;
	private $xdb;
	private $cache;
	private $monolog;

	public function __construct(cache $cache, xdb $xdb, token $token, monolog $monolog)
	{
		$this->cache = $cache;
		$this->xdb = $xdb;
		$this->token = $token;
		$this->monolog = $monolog;
	}

	/*
	 *
	 */

	public function get_token($email, $schema, $source)
	{
		$token = $this->token->gen();

		$cache_key = 'email_validate_token_' . $token;

		$this->cache->set($cache_key, [
			'email' 	=> strtolower($email),
			'schema' 	=> $schema,
			'source'	=> $source,
		], $this->ttl);

		return $token;
	}

	public function validate($token)
	{
		$cache_key = 'email_validate_token_' . $token;

		$data = $this->cache->get($cache_key, true);

		if (!count($data))
		{
			return;
		}

		$this->xdb->set('email_validated', $data['email'], $data, $data['schema']);

		$this->monolog->debug('email ' . $data['email'] . ' validated from ' . $data['source'],
			['schema' => $data['schema']]);

		$this->cache->del($cache_key);
	}

	public function is_validated($email, $schema)
	{
		return $this->xdb->get('email_validated', $email, $schema) ? true : false;
	}
}
