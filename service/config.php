<?php

namespace service;

use service\xdb;
use service\this_group;
use Doctrine\DBAL\Connection as db;
use Predis\Client as predis;
use Monolog\Logger as monolog;

class config
{
	private $monolog;
	private $db;
	private $xdb;
	private $predis;
	private $this_group;
	private $is_cli;

	private $default = [
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

	public function __construct(monolog $monolog, db $db, xdb $xdb,
		predis $predis, this_group $this_group)
	{
		$this->this_group = $this_group;
		$this->monolog = $monolog;
		$this->predis = $predis;
		$this->db = $db;
		$this->xdb = $xdb;
		$this->is_cli = php_sapi_name() === 'cli' ? true : false;
	}

	public function is_valid_key(string $key):bool
	{
		return isset($this->default[$key]);
	}

	public function set(string $name, string $value):void
	{
		$this->xdb->set('setting', $name, ['value' => $value]);

		$this->predis->del($this->this_group->get_schema() . '_config_' . $name);

		// prevent string too long error for eLAS database

		$value = substr($value, 0, 60);

		$this->db->update('config', ['value' => $value, '"default"' => 'f'], ['setting' => $name]);
	}

	public function get(string $key, string $sch = ''):string
	{
		global $s_guest, $s_master;

		if (!$sch)
		{
			$sch = $this->this_group->get_schema();
		}

		if (!$sch)
		{
			$this->monolog->error('no schema set in config:get');
			return '';
		}

		if (isset($this->local_cache[$sch][$key]) && !$this->is_cli)
		{
			return $this->local_cache[$sch][$key];
		}

		$redis_key = $sch . '_config_' . $key;

		if ($this->predis->exists($redis_key))
		{
			return $this->local_cache[$sch][$key] = $this->predis->get($redis_key);
		}

		$row = $this->xdb->get('setting', $key, $sch);

		if ($row)
		{
			$value = $row['data']['value'];
		}
		else if (isset($this->default[$key]))
		{
			$value = $this->default[$key];
		}
		else
		{
			$value = $this->db->fetchColumn('select value from ' . $sch . '.config where setting = ?', [$key]);

			if (!$s_guest && !$s_master)
			{
				$this->xdb->set('setting', $key, ['value' => $value], $sch);
			}
		}

		if (isset($value))
		{
			$this->predis->set($redis_key, $value);
			$this->predis->expire($redis_key, 2592000);
			$this->local_cache[$sch][$key] = $value;
		}

		return $value;
	}
}
