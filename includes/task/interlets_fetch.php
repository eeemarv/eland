<?php

namespace eland\task;

use Predis\Client as Redis;
use Doctrine\DBAL\Connection as db;
use eland\typeahead;
use Monolog\Logger;
use eland\this_group;
use eland\groups;

class interlets_fetch
{
	protected $redis;
	protected $db;
	protected $typeahead;
	protected $monolog;
	protected $this_group;
	protected $groups;

	public function __construct(Redis $redis, db $db, typeahead $typeahead, Logger $monolog, this_group $this_group, groups $groups)
	{
		$this->redis = $redis;
		$this->db = $db;
		$this->typeahead = $typeahead;
		$this->monolog = $monolog;
		$this->this_group = $this_group;
		$this->groups = $groups;
	}

	public function run()
	{
		$r = "<br>\r\n";

		$update_msgs = false;

		$groups = $this->db->fetchAll('select *
			from letsgroups
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

			if ($this->redis->get($this->this_group->get_schema() . '_token_failed_' . $group['remoteapikey'])
				|| $this->redis->get($this->this_group->get_schema() . '_connection_failed_' . $group['domain']))
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
			$err_group = $group['groupname'] . ': ';

			$soapurl = ($group['elassoapurl']) ? $group['elassoapurl'] : $group['url'] . '/soap';
			$soapurl = $soapurl . '/wsdlelas.php?wsdl';
			$apikey = $group['remoteapikey'];
			$client = new \nusoap_client($soapurl, true);
			$err = $client->getError();

			if ($err)
			{

				echo $err_group . 'Can not get connection.' . $r;
				$redis_key = $this->this_group->get_schema() . '_connection_failed_' . $group['domain'];
				$this->redis->set($redis_key, '1');
				$this->redis->expire($redis_key, 21600);  // 6 hours

			}
			else
			{

				$token = $client->call('gettoken', ['apikey' => $apikey]);
				$err = $client->getError();

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
					$redis_key = $this->this_group->get_schema() . '_token_failed_' . $group['remoteapikey'];
					$this->redis->set($redis_key, '1');
					$this->redis->expire($redis_key, 21600);  // 6 hours
				}
			}

			if (!$err)
			{
				try
				{
					$client = new \Goutte\Client();

					$crawler = $client->request('GET', $group['url'] . '/login.php?token=' . $token);

					if ($update_msgs)
					{
						echo 'fetch interlets messages' . $r;
						$this->fetch_msgs($client, $group);
					}
					else
					{
						echo 'fetch interlets typeahead data' . $r;
						$this->fetch_typeahead($client, $group);
					}

					echo '----------------------------------------------------' . $r;
					echo 'end Cron ' . "\n";
					exit;
				}
				catch (Exception $e)
				{
					$err = $e->getMessage();
					echo $err . $r;
					$redis_key = $this->this_group->get_schema() . '_token_failed_' . $group['remoteapikey'];
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

	public function fetch_msgs($client, $group)
	{
		$r = "<br>\r\n";

		$msgs = [];

		$crawler = $client->request('GET', $group['url'] . '/renderindex.php');

		echo $group['url'] . $r;

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
	public function fetch_typeahead($client, $group)
	{
		$crawler = $client->request('GET', $group['url'] . '/rendermembers.php');

		$status_code = $client->getResponse()->getStatus();

		if ($status_code != 200)
		{
			// for an eLAND group which is on another server (this is meant mainly for testing.)

			$crawler = $client->request('GET', $group['url'] . '/users.php?view=list&r=guest&u=elas');

			$status_code = $client->getResponse()->getStatus();

			if ($status_code != 200)
			{
				echo '-- letsgroup url not responsive: ' . $group['url'] . ' status : ' . $status_code . ' --' . $r;

				$redis_key = $this->this_group->get_schema() . '_connection_failed_' . $group['domain'];
				$this->redis->set($redis_key, '1');
				$this->redis->expire($redis_key, 21600);  // 6 hours

				return;
			}

			$users = [];

			$crawler->filter('table tbody tr')
				->each(function ($node) use (&$users)
			{
				$user = [];

				$td = $node->filter('td')->first();

				$user['c'] = $td->text();
				$user['n'] = $td->nextAll()->text();

				$users[] = $user;
			});
		}
		else
		{

			echo $group['url'];

			$users = [];

			$crawler->filter('table tr')
				->first()
				->nextAll()
				->each(function ($node) use (&$users)
			{
				$user = [];

				$td = $node->filter('td')->first();
				$bgcolor = $td->attr('bgcolor');
				$postcode = $td->siblings()->eq(3)->text();

				$user['c'] = $td->text();
				$user['n'] = $td->nextAll()->text();

				if ($bgcolor)
				{
					$user['s'] = (strtolower(substr($bgcolor, 1, 1)) > 'c') ? 2 : 3;
				}

				if ($postcode)
				{
					$user['p'] = $postcode;
				} 

				$users[] = $user;
			}); 

		}

		$redis_data_key = $group['url'] . '_typeahead_data';
		$data_string = json_encode($users);

		if ($data_string != $this->redis->get($redis_data_key))
		{
			$this->typeahead->invalidate_thumbprint('users_active', $group['url'], crc32($data_string));

			$this->redis->set($redis_data_key, $data_string);
		}

		$this->redis->expire($redis_data_key, 86400);		// 1 day

		$redis_refresh_key = $group['domain'] . '_typeahead_updated';
		$this->redis->set($redis_refresh_key, '1');
		$this->redis->expire($redis_refresh_key, 43200);		// 12 hours

		$user_count = count($users);

		$redis_user_count_key = $group['url'] . '_active_user_count';
		$this->redis->set($redis_user_count_key, $user_count);
		$this->redis->expire($redis_user_count_key, 86400); // 1 day

		$this->monolog->debug('cron: typeahead data fetched of ' . $user_count . ' users from group ' . $group['domain'], ['schema' => $this->this_group->get_schema()]);

		echo '----------------------------------------------------' . $r;
		echo $redis_data_key . $r;
		echo $redis_refresh_key . $r;
		echo 'user count: ' . $user_count . $r;	
	}
}
