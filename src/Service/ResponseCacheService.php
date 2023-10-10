<?php declare(strict_types=1);

namespace App\Service;

use Redis;
use Psr\Log\LoggerInterface;

class ResponseCacheService
{
	const STORE_PREFIX = 'response_cache_';
	const TTL_STORE = 5184000; // 60 days

	public function __construct(
		protected Redis $predis,
		protected LoggerInterface $logger
	)
	{
	}

	public function get_thumbprint_from_key(
		string $thumbprint_key,
		string $schema
	):string
	{
		$store_key = self::STORE_PREFIX . $schema;
		$thumbprint = $this->predis->hget($store_key, $thumbprint_key);

		if ($thumbprint !== false)
		{
			if (strlen($thumbprint) !== 8)
			{
				unset($thumbprint);
				$this->predis->hdel($store_key, $thumbprint_key);
			}
		}

		if (!$thumbprint)
		{
			$hash_rnd = hash('crc32b', random_bytes(4));
			$thumbprint = substr_replace($hash_rnd, '-', rand(1, 6), 1);

			$this->logger->debug('No cached response, generated dummy thumbprint ' .
				$thumbprint . ' for ' .
				$thumbprint_key, ['schema' => $schema]);
		}

		return $thumbprint;
	}

	public function get_thumbprint_from_response_body(
		string $response_body
	):string
	{
		return hash('crc32b', $response_body);
	}

	public function clear_cache(string $schema):void
	{
		$store_key = self::STORE_PREFIX . $schema;
		$this->predis->del($store_key);
	}

	public function get_response_body(
		string $thumbprint,
		string $thumbprint_key,
		string $schema
	):string|false
	{
		if (strpos($thumbprint, '-') !== false)
		{
			return false;
		}

		$store_key = self::STORE_PREFIX . $schema;

		$check_thumbprint = $this->predis->hget($store_key, $thumbprint_key);

		if ($check_thumbprint !== $thumbprint)
		{
			return false;
		}

		$resp = $this->predis->hget($store_key, $thumbprint);

		if ($resp === false)
		{
			$this->predis->hdel($store_key, $thumbprint_key);
		}

		return $resp;
	}

	public function store_response_body(
		string $thumbprint_key,
		string $schema,
		string $reponse_body,
	):void
	{
		$store_key = self::STORE_PREFIX . $schema;

		$new_thumbprint = hash('crc32b', $reponse_body);
		$old_thumbprint = $this->predis->hget($store_key, $thumbprint_key);

		if ($new_thumbprint === $old_thumbprint)
		{
			error_log('Reponse Cache thumbprint still valid ' . $new_thumbprint);
			$this->predis->expire($store_key, self::TTL_STORE);
			return;
		}

		$this->predis->hset($store_key, $new_thumbprint, $reponse_body);

		if ($old_thumbprint !== false)
		{
			$this->predis->hdel($store_key, $old_thumbprint);
		}

		$this->predis->hset($store_key, $thumbprint_key, $new_thumbprint);
		$this->predis->expire($store_key, self::TTL_STORE);

		$this->logger->debug('Response cache NEW thumbprint ' .
			$new_thumbprint . ' SET for ' .
			$thumbprint_key, ['schema' => $schema ]);
	}
}
