<?php

namespace eland\task;

use eland\cache;
use eland\model\task;

use eland\schedule;

class fetch_elas_interlets extends task
{
	private $cache;



	public function __construct(cache $cache, schedule $schedule)
	{
		parent::__construct($schedule);
		$this->cache = $cache;
	}

	function process()
	{
		$now = time();
		$now_gmdate = gmdate('Y-m-d H:i:s', $now);

		$elas_interlets_domains = $this->cache->get('elas_interlets_domains');

		$last_fetch = $this->cache->get('elas_interlets_last_fetch');

		$apikey_fails = $this->cache->get('elas_interlets_apikey_fails');

		$apikeys_ignore = [];

		$yesterday = $now - 86400;

		foreach ($apikey_fails as $apikey => $time_failed)
		{
			$failed = strtotime($time_failed . ' UTC');

			if ($failed > $yesterday)
			{
				$apikeys_ignore[$apikey] = $time_failed;
			}
		}

		$diff = array_diff_key($elas_interlets_domains, $last_fetch['users'] ?? []);

		if (count($diff))
		{
			$one_week_ago = $now - 604800;
 
			$one_week_ago = gmdate('Y-m-d H:i:s', $one_week_ago);

			foreach ($diff as $domain => $ary)
			{
				$last_fetch['users'][$domain] = $one_week_ago;
				$last_fetch['msgs'][$domain] = $one_week_ago;

				error_log('-- add to fetch schedule: ' . $domain);
			}
		}

		$last_fetch['users'] = array_intersect_key($last_fetch['users'], $elas_interlets_domains);
		$last_fetch['msgs'] = array_intersect_key($last_fetch['msgs'], $elas_interlets_domains);

		$apikeys = [];

		foreach ($elas_interlets_domains as $domain => $ary)
		{
			foreach ($ary as $sch => $apikey)
			{
				if (!isset($apikeys_ignore[$apikey]))
				{
					$apikeys[$domain] = $apikey;
					continue;
				}
			}
		}

		$v_users = array_intersect_key($last_fetch['users'], $apikeys);
		$v_msgs = array_intersect_key($last_fetch['msgs'], $apikeys);

		$next_domain_users = current(array_keys($v_users, min($v_users)));
		$next_domain_msgs = current(array_keys($v_msgs, min($v_msgs)));

		if (!$next_domain_users && !$next_domain_msgs)
		{
			return;
		}

		$last_fetch_users = $last_fetch[$next_domain_users];
		$last_fetch_msgs = $last_fetch[$next_domain_msgs];

		$subject = $last_fetch_msgs < $last_fetch_users ? 'msgs' : 'users';

		$domain_var = $subject == 'users' ? 'next_domain_users' : 'next_domain_msgs';
		$domain = $$domain_var;
		$next = $last_fetch[$subject][$domain];

		$next = $last_fetch[$next_domain];

		$next = strtotime($next . ' UTC');

		if ($next > $now - 3600)
		{
			return;
		}

		$group_url = 'http://' . $domain;
		$soap_url = $group_url . '/soap/wsdlelas.php?wsdl';
		$apikey = $apikeys[$domain];

		$soap_client = new \nusoap_client($soap_url, true);
		$err = $soap_client->getError();

		if ($err)
		{
			error_log($domain . ' : Can not get connection. Wait 6 hours.');

			$last_fetch[$subject][$domain] = gmdate('Y-m-d H:i:s', $now + 21600);
			$this->cache->set('elas_interlets_last_fetch', $last_fetch);
			return;
		}

		$token = $soap_client->call('gettoken', ['apikey' => $apikey]);
		$err = $soap_client->getError();

		if ($err)
		{
			error_log($domain . ' : Can not get token.');

			$apikey_fails[$apikey] = $now_gmdate;
			$this->cache->set('elas_interlets_apikey_fails', $apikey_fails);
			return;
		}

		if (!$token || $token == '---')
		{
			error_log ($domain . ' : Invalid token.');

			$apikey_fails[$apikey] = $now_gmdate;
			$this->cache->set('elas_interlets_apikey_fails', $apikey_fails);
			return;
		}

		try
		{
			$this->client = new \Goutte\Client();

			$crawler = $this->client->request('GET', $group_url . '/login.php?token=' . $token);

			if ($subject == 'msgs')
			{
				error_log($domain . ': fetch interlets messages');
				$this->fetch_msgs();

				$last_fetch['msgs'][$domain] = $now_gmdate;
			}
			else
			{
				error_log($domain . ' : fetch interlets typeahead data');
				$this->fetch_typeahead();
				$last_fetch['users'][$domain] = $now_gmdate;
			}

			$last_fetch = $this->cache->set('elas_interlets_last_fetch', $last_fetch);
			return;
		}
		catch (Exception $e)
		{
			error_log($e->getMessage());

			$apikey_fails[$apikey] = $now_gmdate;
			$this->cache->set('elas_interlets_apikey_fails', $apikey_fails);
		}

		return;
	}

////

	public function fetch_msgs()
	{
		$msgs = [];

		$crawler = $this->client->request('GET', $this->group['url'] . '/renderindex.php');

		echo $this->group['url'] . $r;

		$msgs_table = $crawler->filter('table')
			->last()
			->filter('tr')
			->first()
			->nextAll()
			->each(function ($node) use (&$msgs, $r)
		{
			$first_td = $node->filter('td')->first();
			$va = $first_td->text();
			$next_tds = $first_td->siblings();
			$a = $next_tds->eq(0)->filter('a');
			$del = $a->filter('del')->count();

			if (!$del)
			{
				$href = $a->attr('href');
				$content = $a->text();
				$user = $next_tds->eq(1)->text();

				list($dummy, $msgid) = explode('=', $href);

				$redis_msg_key = $url . '_interlets_msg_' . $msgid;

				echo '_' . $va . "_  id:" . $msgid . $r;
				echo $content . $r . $user;
				echo $r . $r;

			}

		});
	}

	/*
	 *
	 */
	public function fetch_typeahead()
	{
		$url = 'http://' . $this->group['domain'] . '/';

		$crawler = $this->client->request('GET', $url . 'rendermembers.php');

		$status_code = $this->client->getResponse()->getStatus();

		$users = $h_users = [];

		if ($status_code != 200)
		{
			// for an eLAND group which is on another server (mainly for testing.)

			$crawler = $this->client->request('GET', $url . 'users.php?view=list&r=guest&u=elas');

			$status_code = $this->client->getResponse()->getStatus();

			if ($status_code != 200)
			{
				echo '-- letsgroup url not responsive: ' . $this->group['domain'] . ' status : ' . $status_code . ' --' . $r;

				$redis_key = $this->schema . '_connection_failed_' . $this->group['domain'];
				$this->redis->set($redis_key, '1');
				$this->redis->expire($redis_key, 21600);  // 6 hours

				return;
			}

			$crawler->filter('table tbody tr')
				->each(function ($node) use (&$users)
			{
				$user = $h_user = [];

				$td = $node->filter('td')->first();

				$user['c'] = $td->text();
				$user['n'] = $td->nextAll()->text();

				$users[] = $user;

				$h_users[$user['c']] = ['name' => $user['n']];
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
				$postcode = trim($td->siblings()->eq(3)->text());

				$code = trim($td->text());
				$name = trim($td->nextAll()->text());

				$user['c'] = $code;
				$user['n'] = $name;

				$h_user = ['name'	=> $name];

				if ($bgcolor)
				{
					$user['s'] = strtolower(substr($bgcolor, 1, 1)) > 'c' ? 2 : 3;
					$h_user['status'] = strtolower(substr($bgcolor, 1, 1)) > 'c' ? 'leaving' : 'new';
				}

				if ($postcode)
				{
					$user['p'] = $postcode;
				} 

				$users[] = $user;

				$h_users[$code] = $h_user;
			}); 
		}

		$redis_data_key = $this->group['domain'] . '_typeahead_data';

		$data_string = json_encode($users);

		if ($data_string != $this->redis->get($redis_data_key))
		{
			$this->typeahead->invalidate_thumbprint('users_active', $this->group['domain'], crc32($data_string));

			$this->redis->set($redis_data_key, $data_string);
		}

		$this->redis->expire($redis_data_key, 172800); // 2 days

		error_log($this->xdb->set('typeahead_data', $this->group['domain'], $h_users, 'external'));

/*
		$redis_data_key = $this->group['domain'] . '_typeahead_data';

		$stored_users = $this->redis->hgetall($redis_data_key);

		$diff_ary_1 = array_diff_assoc($h_users, $stored_users);

		foreach ($diff_ary_1 as $k => $v)
		{
			$this->redis->hset($redis_data_key, $k, $v);
			$stored_users[$k] = $v;
		}

		$diff_ary_2 = array_diff_assoc($stored_users, $h_users);

		foreach ($diff_ary_2 as $k => $v)
		{
			$this->redis->hdel($redis_data_key, $k);
		}

		if (count($diff_ary_1) || count($diff_ary_2))
		{
			// invalidate_thumbprint
		}

		$this->redis->expire($redis_data_key, 86400);
*/
	//

		$redis_refresh_key = $this->group['domain'] . '_typeahead_updated';
		$this->redis->set($redis_refresh_key, '1');
		$this->redis->expire($redis_refresh_key, 43200);		// 12 hours

		$user_count = count($users);

		$redis_user_count_key = $this->group['domain'] . '_active_user_count';
		$this->redis->set($redis_user_count_key, $user_count);
		$this->redis->expire($redis_user_count_key, 172800); // 1 day

		$this->monolog->debug('cron: typeahead data fetched of ' . $user_count . ' users from group ' . $this->group['domain'], ['schema' => $this->schema]);

		echo '----------------------------------------------------' . $r;
		echo $redis_data_key . $r;
		echo $redis_refresh_key . $r;
		echo 'user count: ' . $user_count . $r;	
	}


////



	function get_interval()
	{
		return 900;
	}
}
