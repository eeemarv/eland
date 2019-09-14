<?php declare(strict_types=1);

namespace service;

use service\intersystems;
use cnst\menu as cnst_menu;

class menu_nav_system
{
	protected $intersystems;
	protected $s_logins;
	protected $s_schema;
	protected $intersystem_en;
	protected $r_messages;
	protected $r_users_show;

	public function __construct(
		intersystems $intersystems,
		array $s_logins,
		string $s_schema,
		bool $intersystem_en,
		string $r_messages,
		string $r_users_show
	)
	{
		$this->intersystems = $intersystems;
		$this->s_logins = $s_logins;
		$this->s_schema = $s_schema;
		$this->intersystem_en = $intersystem_en;
		$this->r_messages = $r_messages;
		$this->r_users_show = $r_users_show;
	}

	public function has_nav_system():bool
	{
		return ($this->intersystems->get_count($this->s_schema)
			+ count($this->s_logins)) > 1;
	}

	public function get_nav_system():array
	{
		return [];
	}

	public function get_intersystem_en():bool
	{
		return $this->intersystem_en;
	}

}
