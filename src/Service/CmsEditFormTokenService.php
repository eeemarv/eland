<?php declare(strict_types=1);

namespace App\Service;

use Predis\Client as Predis;
use App\Service\TokenGeneratorService;

class CmsEditFormTokenService
{
	protected string $token = '';

	const TTL = 28800; // 8 hours
	const STORE_PREFIX = 'cms_edit_form_token_';

	public function __construct(
		protected Predis $predis,
		protected TokenGeneratorService $token_generator_service
	)
	{
	}

	public function get():string
	{
		$this->token = $this->token_generator_service->gen();
		$key = self::STORE_PREFIX . $this->token;
		$this->predis->set($key, '1');
		$this->predis->expire($key, self::TTL);

		return $this->token;
	}

	public function verify(string $token):bool
	{
		$key = self::STORE_PREFIX . $token;
		$count = $this->predis->del($key);
		return $count === 1;
	}
}
