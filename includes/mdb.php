<?php

class mdb
{
	private $schema;
	private $mdb;
	public $logs;
	public $limit_events;
	public $settings;
	public $docs;
	public $forum;
	public $users;

	public function __construct($schema)
	{
		$this->schema = $schema;
	}

	public function set_schema($schema)
	{
		$this->schema = $schema;
		return this;
	}

	public function get_schema($schema)
	{
		return $this->schema;
	}

	public function connect()
	{
		if (is_object($this->mdb))
		{
			return $this;
		}

		$url = getenv('MONGOLAB_URI');
		$mongo_client = new MongoClient($url);
		$path = parse_url($url, PHP_URL_PATH);
		$this->mdb = $mongo_client->selectDB(trim($path, '/'));

		$logs = $this->schema . '_logs';
		$limit_events = $this->schema . '_limit_events';
		$settings = $this->schema . '_settings';
		$docs = $this->schema . '_docs';
		$forum = $this->schema . '_forum';
		$users = $this->schema . '_users';

		$this->logs = $this->mdb->$logs;
		$this->limit_events = $this->mdb->$limit_events;
		$this->settings = $this->mdb->$settings;
		$this->docs = $this->mdb->$docs;
		$this->forum = $this->mdb->$forum;
		$this->users = $this->mdb->$users;

		return $this;
	}

	public function get_client()
	{
		return $this->mdb;
	}
}
