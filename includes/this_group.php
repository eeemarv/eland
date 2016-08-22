<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Predis\Client as Redis;
use eland\groups;

class this_group
{
	private $db;
	private $redis;
	private $groups;
	private $twig;
	private $schema;
	private $host;

	public function __construct(groups $groups, db $db, Redis $redis, \Twig_Environment $twig)
	{
		$this->db = $db;
		$this->redis = $redis;
		$this->groups = $groups;
		$this->twig = $twig;
		$this->host = $_SERVER['SERVER_NAME'];
		$this->schema = $this->groups->get_schema($this->host);

		if (!$this->schema)
		{
			http_response_code(404);

			echo $this->twig->render('404.twig');
			exit;
		}

		session_set_save_handler(new \eland\redis_session($this->redis));
		session_name('eland');
		session_set_cookie_params(0, '/', '.' . getenv('OVERALL_DOMAIN'));
		session_start();

		$this->db->exec('set search_path to ' . $this->schema);
	}

	public function force($schema)
	{
		$this->schema = $schema;
		$this->host = $this->groups->get_host($schema);
		$this->db->exec('SET search_path TO ' . $schema);
	}

	public function get_schema()
	{
		return $this->schema;
	}

	public function get_host()
	{
		return $this->host;
	}

}
