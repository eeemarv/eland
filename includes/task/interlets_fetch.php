<?php

namespace eland\task;

use Predis\Client as Redis;
use Doctrine\DBAL\Connection as db;
use eland\typeahead;
use Monolog\Logger;
use eland\groups;
use eland\xdb;

class interlets_fetch
{
	protected $redis;
	protected $db;
	protected $xdb;
	protected $typeahead;
	protected $monolog;
	protected $groups;

	protected $group;
	protected $client;

	public function __construct(Redis $redis, db $db, xdb $xdb, typeahead $typeahead, Logger $monolog, groups $groups)
	{
		$this->redis = $redis;
		$this->db = $db;
		$this->xdb = $xdb;
		$this->typeahead = $typeahead;
		$this->monolog = $monolog;
		$this->groups = $groups;
	}

	public function run($schema)
	{
		$r = "<br>\r\n";

		$update_msgs = false;

		$groups = $this->db->fetchAll('select *
			from ' . $schema . '.letsgroups
			where apimethod = \'elassoap\'
				and remoteapikey IS NOT NULL
				and url <> \'\'');

		foreach ($groups as $group)
		{
			$group['domain'] = strtolower(parse_url($group['url'], PHP_URL_HOST));

			if ($this->groups->get_schema($group['domain']))
			{
				unset($group);
				continue;
			}

			if ($this->redis->get($schema . '_token_failed_' . $group['remoteapikey'])
				|| $this->redis->get($schema . '_connection_failed_' . $group['domain']))
			{
				unset($group);
				continue;
			}

			if (!$this->redis->get($group['domain'] . '_typeahead_updated'))
			{
				break;
			}
		/*
			if (!$this->redis->get($group['domain'] . '_msgs_updated'))
			{
				$update_msgs = true;
				break;
			}
		*/
			unset($group);
		}

		if (isset($group))
		{
			$this->group = $group;

			$err_group = $this->group['groupname'] . ': ';

//			$soapurl = $this->group['url'] . '/soap';

			$soapurl = 'http://' . $this->group['domain'] . '/soap';
			$soapurl = $soapurl . '/wsdlelas.php?wsdl';
			$apikey = $this->group['remoteapikey'];

			$soap_client = new \nusoap_client($soapurl, true);
			$err = $soap_client->getError();

			if ($err)
			{

				echo $err_group . 'Can not get connection.' . $r;
				$redis_key = $schema . '_connection_failed_' . $this->group['domain'];
				$this->redis->set($redis_key, '1');
				$this->redis->expire($redis_key, 21600);  // 6 hours

			}
			else
			{

				$token = $soap_client->call('gettoken', ['apikey' => $apikey]);
				$err = $soap_client->getError();

				if ($err)
				{
					echo $err_group . 'Can not get token.' . $r;
				}
				else if (!$token || $token == '---')
				{
					$err = 'invalid token';
					echo $err_group . 'Invalid token.' . $r;
				}

				if ($err)
				{
					$redis_key = $schema . '_token_failed_' . $this->group['remoteapikey'];
					$this->redis->set($redis_key, '1');
					$this->redis->expire($redis_key, 21600);  // 6 hours
				}
			}

			if (!$err)
			{
				try
				{
					$this->client = new \Goutte\Client();

					$crawler = $this->client->request('GET', $this->group['url'] . '/login.php?token=' . $token);

					if ($update_msgs)
					{
						echo 'fetch interlets messages' . $r;
						$this->fetch_msgs();
					}
					else
					{
						echo 'fetch interlets typeahead data' . $r;
						$this->fetch_typeahead($schema);
					}

					echo '----------------------------------------------------' . $r;
					echo 'end Cron ' . "\n";
					exit;
				}
				catch (Exception $e)
				{
					$err = $e->getMessage();
					echo $err . $r;
					$redis_key = $schema . '_token_failed_' . $this->group['remoteapikey'];
					$this->redis->set($redis_key, '1');
					$this->redis->expire($redis_key, 21600);  // 6 hours

				}
			}

			if ($err)
			{
				echo '-- retry after 6 hours --' . $r;
				echo '-- continue --' . $r;
			}
		}
		else
		{
			echo '-- no interlets data fetch needed -- ' . $r;
		}
	}

	public function fetch_msgs()
	{
		$r = "<br>\r\n";

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
	public function fetch_typeahead($schema)
	{
		$r = "<br>\r\n";

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

				$redis_key = $schema . '_connection_failed_' . $this->group['domain'];
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
					$user['s'] = (strtolower(substr($bgcolor, 1, 1)) > 'c') ? 2 : 3;
					$h_user['status'] = (strtolower(substr($bgcolor, 1, 1)) > 'c') ? 'leaving' : 'new';
				}

				if ($postcode)
				{
					$user['p'] = $postcode;
				} 

				$users[] = $user;

				$h_users[$code] = $h_user;
			}); 
		}

		$redis_data_key = $this->group['url'] . '_typeahead_data';
		$redis_data_key_2 = $this->group['domain'] . '_typeahead_data';
		$data_string = json_encode($users);

		if ($data_string != $this->redis->get($redis_data_key))
		{
			$this->typeahead->invalidate_thumbprint('users_active', $this->group['url'], crc32($data_string));

			$this->redis->set($redis_data_key, $data_string);
			$this->redis->set($redis_data_key_2, $data_string);
		}

		$this->redis->expire($redis_data_key, 86400);		// 1 day
		$this->redis->expire($redis_data_key_2, 86400);		// 1 day

		echo $this->xdb->set('typeahead_data', $this->group['domain'], $h_users, 'external');

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

		// to be removed
		$redis_user_count_key = $this->group['url'] . '_active_user_count';
		$this->redis->set($redis_user_count_key, $user_count);
		$this->redis->expire($redis_user_count_key, 86400); // 1 day

		$redis_user_count_key = $this->group['domain'] . '_active_user_count';
		$this->redis->set($redis_user_count_key, $user_count);
		$this->redis->expire($redis_user_count_key, 86400); // 1 day

		$this->monolog->debug('cron: typeahead data fetched of ' . $user_count . ' users from group ' . $this->group['domain'], ['schema' => $schema]);

		echo '----------------------------------------------------' . $r;
		echo $redis_data_key . $r;
		echo $redis_refresh_key . $r;
		echo 'user count: ' . $user_count . $r;	
	}
}
