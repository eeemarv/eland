<?php

namespace schema_task;

use model\schema_task;
use Doctrine\DBAL\Connection as db;
use service\xdb;
use service\cache;
use Monolog\Logger;
use queue\mail;

use service\schedule;
use service\groups;
use service\intersystems;
use service\config;
use service\mail_addr_user;

class saldo extends schema_task
{
	protected $db;
	protected $xdb;
	protected $cache;
	protected $monolog;
	protected $mail;
	protected $intersystems;
	protected $config;
	protected $mail_addr_user;

	public function __construct(
		db $db,
		xdb $xdb,
		cache $cache,
		Logger $monolog,
		mail $mail,
		schedule $schedule,
		groups $groups,
		intersystems $intersystems,
		config $config,
		mail_addr_user $mail_addr_user
	)
	{
		parent::__construct($schedule, $groups);
		$this->db = $db;
		$this->xdb = $xdb;
		$this->cache = $cache;
		$this->monolog = $monolog;
		$this->mail = $mail;
		$this->intersystems = $intersystems;
		$this->config = $config;
		$this->mail_addr_user = $mail_addr_user;
	}

	function process():void
	{

		// vars

		$treshold_time = gmdate('Y-m-d H:i:s', time() - $this->config->get('saldofreqdays', $this->schema) * 86400);

		$users = $news = $new_users = [];
		$leaving_users = $transactions = $messages = [];
		$forum = $intersystem = $docs = [];
		$mailaddr = $mailaddr_public = $saldo_mail = [];

	// get blocks

		$forum_en = $this->config->get('forum_en', $this->schema) ? true : false;
		$intersystem_en = $this->config->get('interlets_en', $this->schema) ? true : false;
		$intersystem_en = $intersystem_en && $this->config->get('template_lets', $this->schema) ? true : false;

		$blocks_sorted = $block_options = [];

		$block_ary = $this->config->get('periodic_mail_block_ary', $this->schema);

		$block_ary = explode(',', ltrim($block_ary, '+'));

		foreach ($block_ary as $v)
		{
			[$block, $option] = explode('.', $v);

			if ($block === 'forum' && !$forum_en)
			{
				continue;
			}

			if ($block === 'interlets' && !$intersystem_en)
			{
				continue;
			}

			$block_options[$block] = $option;
			$blocks_sorted[] = $block;
		}

	// fetch all active users

		$rs = $this->db->prepare('select u.id,
				u.name, u.saldo, u.status,
				u.letscode, u.postcode, u.cron_saldo
			from ' . $this->schema . '.users u
			where u.status in (1, 2)');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$users[$row['id']] = $row;
		}

	// fetch mail addresses & cron_saldo

		$st = $this->db->prepare('select u.id, c.value, c.flag_public
			from ' . $this->schema . '.users u, ' .
				$this->schema . '.contact c, ' .
				$this->schema . '.type_contact tc
			where u.status in (1, 2)
				and u.id = c.id_user
				and c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'');

		$st->execute();

		while ($row = $st->fetch())
		{
			$user_id = $row['id'];
			$mail = $row['value'];
			$mailaddr[$user_id][] = $mail;
			$mailaddr_public[$user_id][] = $row['flag_public'];

			if (!$users[$user_id] || !$users[$user_id]['cron_saldo'])
			{
				continue;
			}

			$saldo_mail[$user_id] = true;
		}

	// fetch images

		if (isset($block_options['messages']))
		{
			$image_ary = [];

			$rs = $this->db->prepare('select m.id, p."PictureFile"
				from ' . $this->schema . '.msgpictures p, ' .
					$this->schema . '.messages m
				where p.msgid = m.id
					and m.cdate >= ?', [$treshold_time]);

			$rs->bindValue(1, $treshold_time);
			$rs->execute();

			while ($row = $rs->fetch())
			{
				$image_ary[$row['id']][] = $row['PictureFile'];
			}

		// fetch addresses

			$addr = $addr_public = $addr_p = [];

			$rs = $this->db->prepare('select u.id, c.value, c.flag_public
				from ' . $this->schema . '.users u, ' .
					$this->schema . '.contact c, ' .
					$this->schema . '.type_contact tc
				where u.status in (1, 2)
					and u.id = c.id_user
					and c.id_type_contact = tc.id
					and tc.abbrev = \'adr\'');

			$rs->execute();

			while ($row = $rs->fetch())
			{
				$addr[$row['id']] = $row['value'];
				$addr_public[$row['id']] = $row['flag_public'];
				$users[$row['id']]['adr'] = $row['value'];
			}

		// fetch messages

			$rs = $this->db->prepare('select m.id, m.content,
					m."Description" as description,
					m.msg_type, m.id_user,
					m.amount, m.units
				from ' . $this->schema . '.messages m, ' .
					$this->schema . '.users u
				where m.id_user = u.id
					and u.status IN (1, 2)
					and m.cdate >= ?
				order BY m.cdate DESC');

			$rs->bindValue(1, $treshold_time);
			$rs->execute();

			while ($row = $rs->fetch())
			{
				$uid = $row['id_user'];
				$adr = isset($addr_public[$uid]) && $addr_public[$uid] ? $addr[$uid] : '';

				$row['type'] = $row['msg_type'] ? 'offer' : 'want';
				$row['offer'] = $row['type'] == 'offer' ? true : false;
				$row['want'] = $row['type'] == 'want' ? true : false;
				$row['images'] = $image_ary[$row['id']];
				$row['mail'] = $mailaddr[$uid] ?? '';
				$row['addr'] = str_replace(' ', '+', $adr);
				$row['adr'] = $adr;

				$messages[] = $row;
			}
		}

	// interSystem messages

		if (isset($block_options['interlets']) && $block_options['interlets'] == 'recent')
		{
			$eland_ary = $this->intersystems->get_eland($this->schema);

			foreach ($eland_ary as $sch => $d)
			{
				$intersystem_msgs = [];

				$rs = $this->db->prepare('select m.id, m.content,
						m."Description" as description,
						m.msg_type, m.id_user as user_id,
						m.amount, m.units
					from ' . $sch . '.messages m, ' .
						$sch . '.users u
					where m.id_user = u.id
						and m.local = \'f\'
						and u.status in (1, 2)
						and m.cdate >= ?
					order by m.cdate DESC');

				$rs->bindValue(1, $treshold_time);
				$rs->execute();

				while ($row = $rs->fetch())
				{
					$row['type'] = $row['msg_type'] ? 'offer' : 'want';
					$row['offer'] = $row['type'] == 'offer' ? true : false;
					$row['want'] = $row['type'] == 'want' ? true : false;
					$row['user'] = $row['letscode'] . ' ' . $row['name'];

					$intersystem_msgs[] = $row;
				}

				if (count($intersystem_msgs))
				{
					$intersystem[] = [
						'eland_server'	=> true,
						'elas'			=> false,
						'schema'		=> $sch,
						'messages'		=> $intersystem_msgs,
					];
				}
			}

			$elas_ary = $this->intersystems->get_elas($this->schema);

			foreach ($elas_ary as $group_id => $ary)
			{
				$intersystem_msgs = [];

				$domain = strtolower(parse_url($ary['url'], PHP_URL_HOST)); // TODO: switch to $ary['domain']

				$elas_msgs = $this->cache->get($domain . '_elas_interlets_msgs');

				foreach ($elas_msgs as $m)
				{
					if ($m['fetched_at'] < $treshold_time)
					{
						continue;
					}

					$m['type'] = $m['ow'] == 'o' ? 'offer' : 'want';
					$m['offer'] = $m['type'] == 'offer' ? true : false;
					$m['want'] = $m['type'] == 'want' ? true : false;

					$intersystem_msgs[] = $m;
				}

				if (count($intersystem_msgs))
				{
					$intersystem[] = [
						'elas'			=> true,
						'eland_server'	=> false,
						'system_name'	=> $ary['groupname'],
						'messages'		=> $intersystem_msgs,
					];
				}
			}
		}

	// news

		if (isset($block_options['news']))
		{
			$rows = $this->xdb->get_many([
				'agg_schema' => $this->schema,
				'agg_type' => 'news_access',
			]);

			foreach ($rows as $row)
			{
				$news_access_ary[$row['eland_id']] = $row['data']['access'];
			}

			$query = 'select n.*
				from ' . $this->schema . '.news n
				where n.approved = \'t\'
					and n.published = \'t\' ';

			$query .= $block_options['news'] == 'recent' ? 'and n.cdate > ? ' : '';
			$query .= 'order by n.itemdate ';
			$query .= $this->config->get('news_order_asc', $this->schema) === '1' ? 'asc' : 'desc';

			$rs = $this->db->prepare($query);

			if ($block_options['news'] == 'recent')
			{
				$rs->bindValue(1, $treshold_time);
			}

			$rs->execute();

			while ($row = $rs->fetch())
			{
				if (isset($news_access_ary[$row['id']]))
				{
					$row['access'] = $news_access_ary[$row['id']];
				}
				else
				{
					$this->xdb->set('news_access',
						$news_id,
						['access' => 'interlets'],
						$this->schema);

					$row['access'] = 'interlets';
				}

				if (!in_array($row['access'], ['users', 'interlets']))
				{
					continue;
				}

				$news[] = $row;
			}
		}

	// new users

		if (isset($block_options['new_users']))
		{

			$rs = $this->db->prepare('select u.id
				from ' . $this->schema . '.users u
				where u.status = 1
					and u.adate > ?');

			$time = gmdate('Y-m-d H:i:s', time() - $this->config->get('newuserdays', $this->schema) * 86400);
			$time = ($block_options['new_users'] === 'recent') ? $treshold_time: $time;

			$rs->bindValue(1, $time);
			$rs->execute();

			while ($row = $rs->fetch())
			{
				$new_users[] = $row['id'];
			}
		}

	// leaving users

		if (isset($block_options['leaving_users']))
		{
			$query = 'select u.id
				from ' . $this->schema . '.users u
				where u.status = 2';

			if ($block_options['leaving_users'] === 'recent')
			{
				$query .= ' and mdate > ?';
			}

			$rs = $this->db->prepare($query);

			if ($block_options['leaving_users'] === 'recent')
			{
				$rs->bindValue(1, $treshold_time);
			}

			$rs->execute();

			while ($row = $rs->fetch())
			{
				$leaving_users[] = $row['id'];
			}
		}

	// transactions

		if (isset($block_options['transactions']))
		{
			$rs = $this->db->prepare('select t.*
				from ' . $this->schema . '.transactions t
				where t.cdate > ?');

			$rs->bindValue(1, $treshold_time);
			$rs->execute();

			while ($row = $rs->fetch())
			{
				$transactions[] = $row;
			}
		}

	// forum

		$forum_topics = $forum_topics_replied = [];

		if (isset($block_options['forum']))
		{
			// new topics

			$rows = $this->xdb->get_many([
				'agg_schema' => $this->schema,
				'agg_type' => 'forum',
				'data->>\'subject\'' => ['is not null'],
				'ts' => ['>' => $treshold_time],
				'access' => ['users', 'interlets']], 'order by event_time desc');

			if (count($rows))
			{
				foreach ($rows as $row)
				{
					$data = $row['data'];

					$forum[] = [
						'subject'	=> $data['subject'],
						'content'	=> $data['content'],
						'id'		=> $row['eland_id'],
						'ts'		=> $row['ts'],
					];

					$forum_topics[$row['eland_id']] = true;
				}
			}

			// new replies

			$rows = $this->xdb->get_many(['agg_schema' => $this->schema,
				'agg_type' => 'forum',
				'data->>\'parent_id\'' => ['is not null'],
				'ts' => ['>' => $treshold_time]], 'order by event_time desc');

			foreach ($rows as $row)
			{
				$data = $row['data'];

				if (!isset($forum_topics[$data['parent_id']]))
				{
					$forum_topics_replied[] = $this->schema . '_forum_' . $data['parent_id'];
				}
			}

			if (count($forum_topics_replied))
			{
				$rows = $this->xdb->get_many(['agg_id_ary' => $forum_topics_replied,
					'access' => ['users', 'interlets']]);

				if (count($rows))
				{
					foreach ($rows as $row)
					{
						$data = $row['data'];

						$forum[] = [
							'subject'	=> $data['subject'],
							'content'	=> $data['content'],
							'id'		=> $row['eland_id'],
							'ts'		=> $row['ts'],
						];
					}
				}
			}
		}

	// docs

		if (isset($block_options['docs']))
		{
			$rows = $this->xdb->get_many(['agg_schema' => $this->schema,
				'agg_type' => 'doc',
				'ts' => ['>' => $treshold_time],
				'access' => ['users', 'interlets']], 'order by event_time desc');

			if (count($rows))
			{
				foreach ($rows as $row)
				{
					$data = $row['data'];

					$docs[] = [
						'name'			=> $data['name'] ?? $data['org_filename'],
						'filename'		=> $data['filename'],
						'ts'			=> $row['ts'],
					];
				}
			}
		}

	//

		$vars = [
			'new_users'				=> $new_users,
			'leaving_users'			=> $leaving_users,
			'news'					=> $news,
			'transactions'			=> $transactions,
			'forum'					=> $forum,
			'docs'					=> $docs,
			'messages'				=> $messages,
			'intersystem'			=> $intersystem,
			'block_options'			=> $block_options,
			'blocks_sorted'			=> $blocks_sorted,
		];

	// queue mail

		$log_to = [];

		foreach ($saldo_mail as $id => $b)
		{
			$to = $this->mail_addr_user->get($id, $this->schema);

			if (!count($to))
			{
				$this->monolog->info('No periodic mail queued for user ' .
				link_user($id, $this->schema, false) . ' because no email address.',
				['schema' => $this->schema]);

				continue;
			}

			if (isset($users_geo[$id]))
			{
				$users[$id]['geo'] = $users_geo[$id];
			}

			//
			if ($users[$id]['minlimit'] === -999999999)
			{
				$users[$id]['minlimit'] = '';
			}

			if ($users[$id]['maxlimit'] === 999999999)
			{
				$users[$id]['maxlimit'] = '';
			}

			$this->mail->queue([
				'email_validate'	=> true,
				'schema'			=> $this->schema,
				'to'				=> $to,
				'template'			=> 'periodic_overview/periodic_overview',
				'vars'				=> array_merge($vars, [
					'user'			=> $users[$id],
				]),
			], random_int(0, 5000));

			$log_str = $users[$id]['letscode'] . ' ' . $users[$id]['name'];
			$log_str .= ' (' . $id . ' to: ' . json_encode($to) . ' )';
			$log_to[] = $log_str;
		}

		if (count($log_to))
		{
			$this->monolog->info('Saldomail queued: ' .
				implode(', ', $log_to), ['schema' => $this->schema]);
		}
		else
		{
			$this->monolog->info('Saldomail NOT queued (no users)',
				['schema' => $this->schema]);
		}

		return;
	}

	public function is_enabled():bool
	{
		return $this->config->get('saldofreqdays', $this->schema) ? true : false;
	}

	public function get_interval():int
	{
		if (isset($this->schema))
		{
			$days = $this->config->get('saldofreqdays', $this->schema);
			$days = $days < 1 ? 7 : $days;

			return 86400 * $days;
		}

		return 86400;
	}
}
