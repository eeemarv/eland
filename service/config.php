<?php

namespace service;

use service\xdb;
use Doctrine\DBAL\Connection as db;
use Predis\Client as predis;

class config
{
	protected $db;
	protected $xdb;
	protected $predis;
	protected $is_cli;

	protected $default = [
		'preset_minlimit'					=> '',
		'preset_maxlimit'					=> '',
		'users_can_edit_username'			=> '0',
		'users_can_edit_fullname'			=> '0',
		'registration_en'					=> '0',
		'registration_top_text'				=> '',
		'registration_bottom_text'			=> '',
		'registration_success_text'			=> '',
		'registration_success_url'			=> '',
		'forum_en'							=> '0',
		'news_order_asc'					=> '0',
		'css'								=> '0',
		'msgs_days_default'					=> '365',
		'balance_equilibrium'				=> '0',
		'date_format'						=> '%e %b %Y, %H:%M:%S',
		'periodic_mail_block_ary'			=> '+messages.recent',
		'default_landing_page'				=> 'messages',
		'homepage_url'						=> '',
		'template_lets'						=> '1',
		'interlets_en'						=> '1',
	];

	public function __construct(
		db $db,
		xdb $xdb,
		predis $predis
	)
	{
		$this->predis = $predis;
		$this->db = $db;
		$this->xdb = $xdb;
		$this->is_cli = php_sapi_name() === 'cli' ? true : false;
	}

	public function exists(string $name, string $schema):bool
	{
		return 0 < $this->xdb->count('setting', $name, $schema);
	}

	public function get_uncached(string $key, string $schema):string
	{
		$row = $this->xdb->get('setting', $key, $schema);

		if ($row)
		{
			return $row['data']['value'];
		}

		return '';
	}

	public function set(string $name, string $schema, string $value):void
	{
		$this->xdb->set('setting', $name, ['value' => $value], $schema);
		$this->predis->del($schema . '_config_' . $name);

		// here no update for eLAS database
	}

	public function get(string $key, string $schema):string
	{
		if (isset($this->local_cache[$schema][$key]) && !$this->is_cli)
		{
			return $this->local_cache[$schema][$key];
		}

		$redis_key = $schema . '_config_' . $key;

		if ($this->predis->exists($redis_key))
		{
			return $this->local_cache[$schema][$key] = $this->predis->get($redis_key);
		}

		$row = $this->xdb->get('setting', $key, $schema);

		if ($row)
		{
			$value = $row['data']['value'];
		}
		else if (isset($this->default[$key]))
		{
			$value = $this->default[$key];
		}

		if (isset($value))
		{
			$this->predis->set($redis_key, $value);
			$this->predis->expire($redis_key, 2592000);
			$this->local_cache[$schema][$key] = $value;
		}

		return $value;
	}
}
