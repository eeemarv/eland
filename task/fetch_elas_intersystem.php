<?php

namespace task;

use service\cache;
use Predis\Client as redis;
use service\typeahead;
use Monolog\Logger;
use util\cnst;

class fetch_elas_intersystem
{
	protected $cache;
	protected $redis;
	protected $typeahead;
	protected $monolog;
	protected $client;
	protected $url;
	protected $domain;
	protected $now;
	protected $now_gmdate;
	protected $last_fetch;
	protected $apikeys_fails;
	protected $elas_domains;

	public function __construct(
		cache $cache,
		redis $redis,
		typeahead $typeahead,
		Logger $monolog
	)
	{
		$this->cache = $cache;
		$this->redis = $redis;
		$this->typeahead = $typeahead;
		$this->monolog = $monolog;
	}

	function process():void
	{
		$this->now = time();
		$this->now_gmdate = gmdate('Y-m-d H:i:s', $this->now);

		$this->elas_domains = $this->cache->get(cnst::ELAS_CACHE_KEY['domains']);
		$this->last_fetch = $this->cache->get(cnst::ELAS_CACHE_KEY['last_fetch']);
		$this->apikeys_fails = $this->cache->get(cnst::ELAS_CACHE_KEY['apikey_fails']);

		error_log('-- Last fetch --');
		error_log(json_encode($this->last_fetch));
		error_log('-- apikey fails --');
		error_log(json_encode($this->apikey_fails));

		$apikeys_ignore = $apikeys_fails_cleanup = [];

		$yesterday = $this->now - 86400;

		foreach ($this->apikeys_fails as $apikey => $time_failed)
		{
			$failed = strtotime($time_failed . ' UTC');

			if ($failed > $yesterday)
			{
				$apikeys_ignore[$apikey] = $time_failed;
				continue;
			}

			$apikeys_fails_cleanup[] = $apikey;
		}

		if (count($apikeys_ignore))
		{
			error_log('Apikeys ignored (failed previously):');
			error_log(json_encode($apikeys_ignore));
		}

		foreach ($apikeys_fails_cleanup as $apikey)
		{
			unset($this->apikeys_fails[$apikey]);
		}

		$diff = array_diff_key($this->elas_domains, $this->last_fetch['users'] ?? []);

		if (count($diff))
		{
			$one_week_ago = $this->now - 604800;

			$one_week_ago = gmdate('Y-m-d H:i:s', $one_week_ago);

			foreach ($diff as $domain => $ary)
			{
				$this->last_fetch['users'][$domain] = $one_week_ago;
				$this->last_fetch['msgs'][$domain] = $one_week_ago;

				error_log('-- add to fetch schedule: ' . $domain);
			}
		}

		$this->last_fetch['users'] = array_intersect_key($this->last_fetch['users'] ?? [], $this->elas_domains);
		$this->last_fetch['msgs'] = array_intersect_key($this->last_fetch['msgs'] ?? [], $this->elas_domains);

		$apikeys = [];

		foreach ($this->elas_domains as $domain => $ary)
		{
			foreach ($ary as $sch => $sch_ary)
			{
				if (!isset($sch_ary['remoteapikey']))
				{
					error_log('Remote apikey is not set for domain ' . $domain .
						' in schema ' . $sch);
					continue;
				}

				if (!isset($apikeys_ignore[$sch_ary['remoteapikey']]))
				{
					$apikeys[$domain] = $sch_ary['remoteapikey'];
					continue;
				}
			}
		}

		error_log('-- apikeys --');
		error_log(json_encode($apikeys));

		$v_users = array_intersect_key($this->last_fetch['users'] ?? [], $apikeys);
		$v_msgs = array_intersect_key($this->last_fetch['msgs'] ?? [], $apikeys);

		if (!count($v_users) || !count($v_msgs))
		{
			$this->update_cache();
			error_log('e 1');
			return;
		}

		$next_domain_users = current(array_keys($v_users, min($v_users)));
		$next_domain_msgs = current(array_keys($v_msgs, min($v_msgs)));

		if (!$next_domain_users || !$next_domain_msgs)
		{
			$this->update_cache();
			error_log('e 2');
			return;
		}

		error_log('next domain users : ' . $next_domain_users);
		error_log('next domain msgs : ' . $next_domain_msgs);

		$last_fetch_users = $this->last_fetch['users'][$next_domain_users];
		$last_fetch_msgs = $this->last_fetch['msgs'][$next_domain_msgs];

		$subject = $last_fetch_msgs < $last_fetch_users ? 'msgs' : 'users';

		$this->domain = ${'next_domain_' . $subject};
		$next = $this->last_fetch[$subject][$this->domain];

		$next = strtotime($next . ' UTC');

		if ($next > $this->now - 14400)
		{
			$this->update_cache();
			error_log('e 14400');
			return;
		}

		$this->url = 'http://' . $this->domain;
		$soap_url = $this->url . '/soap/wsdlelas.php?wsdl';
		$apikey = $apikeys[$this->domain];

		$soap_client = new \nusoap_client($soap_url, true);
		$err = $soap_client->getError();

		if ($err)
		{
			error_log($this->domain . ' : Can not get connection. Wait 6 hours.');

			$this->last_fetch[$subject][$this->domain] = gmdate('Y-m-d H:i:s', $this->now + 21600);
			$this->update_cache();
			return;
		}

		$token = $soap_client->call('gettoken', ['apikey' => $apikey]);
		$err = $soap_client->getError();

		if ($err)
		{
			error_log($this->domain . ' : Can not get token.');

			$this->apikeys_fails[$apikey] = $this->now_gmdate;
			$this->update_cache();
			return;
		}

		if (!$token || $token == '---')
		{
			error_log ($this->domain . ' : Invalid token.');

			$this->apikeys_fails[$apikey] = $this->now_gmdate;
			$this->update_cache();
			return;
		}

		try
		{
			$this->client = new \Goutte\Client();

			$crawler = $this->client->request('GET', $this->url . '/login.php?token=' . $token);

			if ($subject == 'msgs')
			{
				error_log($this->domain . ': fetch interSystem messages');
				$this->fetch_msgs();
			}
			else
			{
				error_log($this->domain . ' : fetch interSystem users data');
				$this->fetch_users();
			}

			$this->update_cache();
			return;
		}
		catch (Exception $e)
		{
			error_log($e->getMessage());

			$this->apikeys_fails[$apikey] = $this->now_gmdate;
			$this->update_cache();

		}

		return;
	}

	/**
	*
	*/

	protected function update_cache():void
	{
		$this->cache->set(cnst::ELAS_CACHE_KEY['last_fetch'], $this->last_fetch);
		$this->cache->set(cnst::ELAS_CACHE_KEY['apikey_fails'], $this->apikeys_fails);
		error_log('update cache');
	}

	/**
	 *
	 */

	protected function fetch_msgs():void
	{
		$msgs = [];

		$cached = $this->cache->get($this->domain . '_elas_interlets_msgs');

		$crawler = $this->client->request('GET', $this->url . '/renderindex.php');

		$status_code = $this->client->getResponse()->getStatus();

		if ($status_code != 200)
		{
			// for an eLAND group which is on another server (mainly for testing.)

			$crawler = $this->client->request('GET', $this->url . '/messages.php?view=list&r=guest&u=elas');

			$status_code = $this->client->getResponse()->getStatus();

			if ($status_code != 200)
			{
				error_log($this->domain . ' not responsive: status : ' . $status_code . ' --');

				$this->last_fetch['msgs'][$this->domain] = gmdate('Y-m-d H:i:s', $this->now + 21600);

				return;
			}

			$crawler->filter('table tbody tr')
				->each(function ($node) use (&$msgs, $cached)
			{
				$msg = [];

				$td = $node->filter('td')->first();

				$va = $td->text();
				$va = substr($va, 0, 1);

				$next_tds = $td->siblings();
				$a = $next_tds->eq(0)->filter('a');

				$href = $a->attr('href');
				$content = $a->text();
				$user = $next_tds->eq(1)->text();

				[$dummy, $id] = explode('=', $href);

				$id = rtrim($id, '&r ');

				$c = $cached[$id] ?? false;

				$count = is_array($c) ? $c['fetch_count'] + 1 : 0;

				$msg = [
					'id'			=> $id,
					'ow'			=> $va == 'v' || $va == 'V' ? 'w' : 'o',
					'content'		=> trim($content),
					'user'			=> $user,
					'fetch_count'	=> $count,
					'fetched_at'	=> is_array($c) ? $c['fetched_at'] : $this->now_gmdate,
				];

//				error_log($this->domain . ' _' . $va . '_  id:' . $id . ' c: ' . $content . ' -- ' . $user);

				$msgs[$id] = $msg;
			});

			error_log($this->domain . ' : fetched ' . count($msgs) . ' messages');

			$this->cache->set($this->domain . '_elas_interlets_msgs', $msgs);
			$this->last_fetch['msgs'][$this->domain] = $this->now_gmdate;

			return;
		}

		$msgs_table = $crawler->filter('table')
			->last()
			->filter('tr')
			->first()
			->nextAll();

		if (!$msgs_table->count())
		{
			return;
		}

		$msgs_table->each(function ($node) use (&$msgs, $cached)
		{
			$first_td = $node->filter('td')->first();
			$va = $first_td->text();
			$va = substr($va, 0, 1);

			$next_tds = $first_td->siblings();

			if (!$next_tds->count())
			{
				return;
			}

			$eq0 = $next_tds->eq(0);

			if (!$eq0->count())
			{
				return;
			}

			$eq1 = $next_tds->eq(1);

			if (!$eq1->count())
			{
				return;
			}

			$a = $eq0->filter('a');
			$del = $a->filter('del')->count();

			if (!$del && $next_tds->count())
			{
				$href = $a->attr('href');
				$content = $a->text();
				$user = $eq1->text();

				$user = rtrim($user, ') ');
				$pos = strrpos($user, '(');

				$username = substr($user, 0, $pos - 1);
				$letscode = substr($user, $pos + 1);

				$user = $letscode . ' ' . $username;

				[$dummy, $id] = explode('=', $href);

				$c = $cached[$id] ?? false;

				$count = is_array($c) ? $c['fetch_count'] + 1 : 0;

				$msg = [
					'id'			=> $id,
					'ow'			=> $va == 'v' || $va == 'V' ? 'w' : 'o',
					'content'		=> trim($content),
					'user'			=> $user,
					'fetch_count'	=> $count,
					'fetched_at'	=> is_array($c) ? $c['fetched_at'] : $this->now_gmdate,
				];

				$msgs[$id] = $msg;
			}

		});

		error_log($this->domain . ' : fetched ' . count($msgs) . ' messages');

		$this->cache->set($this->domain . '_elas_interlets_msgs', $msgs);
		$this->last_fetch['msgs'][$this->domain] = $this->now_gmdate;

		return;
	}

	/*
	*
	*/

	protected function fetch_users():void
	{
		$crawler = $this->client->request('GET',
			$this->url . '/rendermembers.php');

		$status_code = $this->client
			->getResponse()
			->getStatus();

		$users = [];

		if ($status_code != 200)
		{
			// for an eLAND group which is on another server (mainly for testing.)

			$crawler = $this->client->request('GET', $this->url . '/users.php?view=list&r=guest&u=elas');

			$status_code = $this->client->getResponse()->getStatus();

			if ($status_code != 200)
			{
				error_log($this->domain . ' not responsive: status : ' . $status_code . ' --');

				$this->last_fetch['users'][$this->domain] = gmdate('Y-m-d H:i:s', $this->now + 21600);
				$this->update_cache();

				return;
			}

			$crawler->filter('table tbody tr')
				->each(function ($node) use (&$users)
			{
				$user = [];

				$td = $node->filter('td')->first();

				$user['c'] = $td->text();
				$user['n'] = $td->nextAll()->text();

				$next_tds = $td->siblings();
				$a = $next_tds->eq(0)->filter('a');
				$href = $a->attr('href');
				[$dummy, $id] = explode('=', $href);

				$user['b'] = trim($next_tds->eq(5)->text());

				$users[$id] = $user;
			});
		}
		else
		{
			$crawler->filter('table tr')
				->first()
				->nextAll()
				->each(function ($node) use (&$users)
			{
				$user = $h_user = [];

				$td = $node->filter('td')->first();

				$bgcolor = trim($td->attr('bgcolor'));
				$next_tds = $td->siblings();
				$postcode = trim($next_tds->eq(3)->text());
				$balance = trim($next_tds->eq(5)->text());

				$code = trim($td->text());
				$name = trim($td->nextAll()->text());

				$a = $next_tds->eq(0)->filter('a');
				$href = $a->attr('href');
				[$dummy, $id] = explode('=', $href);

				$user['c'] = $code;
				$user['n'] = $name;
				$user['b'] = $balance;

				if ($bgcolor)
				{
					$user['s'] = strtolower(substr($bgcolor, 1, 1)) > 'c' ? 2 : 3;
				}

				if ($postcode)
				{
					$user['p'] = $postcode;
				}

				$users[$id] = $user;
			});
		}

		$new_thumbprint = crc32(json_encode($users));

		foreach($this->elas_domains[$this->domain] as $schema => $ary)
		{
			if (!isset($ary['group_id']))
			{
				continue;
			}

			$params = [
				'schema'	=> $schema,
				'group_id'	=> $ary['group_id'],
			];

			$this->typeahead->set_thumbprint(
				'elas_intersystem_accounts',
				$params,
				$new_thumbprint
			);
		}

		$this->cache->set($this->domain . '_typeahead_data', $users);

		$this->last_fetch['users'][$this->domain] = $this->now_gmdate;

		$redis_key = $this->domain . '_active_user_count';
		$this->redis->set($redis_key, count($users));
		$this->redis->expire($redis_key, 86400); // 1 day

		error_log($this->domain . ' typeahead data fetched of ' . count($users) . ' users');
	}
}
