<?php

class elas_mongo
{
	private $schema;
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
	}

	public function connect()
	{
		if (is_object($this->logs))
		{
			return $this;
		}

		$url = getenv('MONGOLAB_URI');
		$mongo_client = new MongoClient($url);
		$path = parse_url($url, PHP_URL_PATH);
		$mdb = $mongo_client->selectDB(trim($path, '/'));

		$log_collection = $this->schema . '_logs';
		$limit_events_collection = $this->schema . '_limit_events';
		$settings_collection = $this->schema . '_settings';
		$docs = $this->schema . '_docs';
		$forum = $this->schema . '_forum';
		$users = $this->schema . '_users';

		$this->logs = $mdb->$log_collection;
		$this->limit_events = $mdb->$limit_events_collection;
		$this->settings = $mdb->$settings_collection;
		$this->docs = $mdb->$docs;
		$this->forum = $mdb->$forum;
		$this->users = $mdb->$users;

		return $this;
	}
}
