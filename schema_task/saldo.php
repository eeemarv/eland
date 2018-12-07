<?php

namespace schema_task;

use model\schema_task;
use Doctrine\DBAL\Connection as db;
use Predis\Client as Redis;
use service\xdb;
use service\cache;
use Monolog\Logger;
use queue\mail;
use service\date_format;
use service\distance;

use service\schedule;
use service\groups;
use service\interlets_groups;
use service\config;
use service\mail_addr_user;

class saldo extends schema_task
{
	protected $db;
	protected $xdb;
	protected $redis;
	protected $cache;
	protected $monolog;
	protected $mail;
	protected $s3_img_url;
	protected $s3_doc_url;
	protected $protocol;
	protected $date_format;
	protected $distance;
	protected $interlets_groups;
	protected $config;
	protected $mail_addr_user;

	public function __construct(
		db $db,
		xdb $xdb,
		Redis $redis,
		cache $cache,
		Logger $monolog,
		mail $mail,
		string $s3_img_url,
		string $s3_doc_url,
		string $protocol,
		date_format $date_format,
		distance $distance,
		schedule $schedule,
		groups $groups,
		interlets_groups $interlets_groups,
		config $config,
		mail_addr_user $mail_addr_user
	)
	{
		parent::__construct($schedule, $groups);
		$this->db = $db;
		$this->xdb = $xdb;
		$this->redis = $redis;
		$this->cache = $cache;
		$this->monolog = $monolog;
		$this->mail = $mail;
		$this->s3_img_url = $s3_img_url;
		$this->s3_doc_url = $s3_doc_url;
		$this->protocol = $protocol;
		$this->date_format = $date_format;
		$this->interlets_groups = $interlets_groups;
		$this->config = $config;
		$this->mail_addr_user = $mail_addr_user;
	}

	function process():void
	{

		// vars

		$host = $this->groups->get_host($this->schema);

		if (!$host)
		{
			return;
		}

		$base_url = $this->protocol . $host;

		$treshold_time = gmdate('Y-m-d H:i:s', time() - $this->config->get('saldofreqdays', $this->schema) * 86400);

		$msg_url = $base_url . '/messages.php?id=';
		$msgs_url = $base_url . '/messages.php?src=p';
		$news_url = $base_url . '/news.php?id=';
		$user_url = $base_url . '/users.php?id=';
		$login_url = $base_url . '/login.php?login=';
		$new_message_url = $base_url . '/messages.php?add=1';
		$new_transaction_url = $base_url . '/transactions.php?add=1';
		$account_edit_url = $base_url . '/users.php?edit=';

		$users = $news = $new_users = [];
		$leaving_users = $transactions = $messages = [];
		$forum = $interlets = $docs = [];
		$mailaddr = $mailaddr_public = $saldo_mail = [];

	// get blocks

		$forum_en = $this->config->get('forum_en', $this->schema) ? true : false;
		$interlets_en = $this->config->get('interlets_en', $this->schema) ? true : false;
		$interlets_en = $interlets_en && $this->config->get('template_lets', $this->schema) ? true : false;

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

			if ($block === 'interlets' && !$interlets_en)
			{
				continue;
			}

			$block_options[$block] = $option;
			$blocks_sorted[] = $block;
		}

	// fetch active users

		$rs = $this->db->prepare('select u.id,
				u.name, u.saldo, u.status, u.minlimit, u.maxlimit,
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

				$geo = $this->cache->get('geo_' . $row['value']);

				if (count($geo))
				{
					if (isset($geo['lat']) && isset($geo['lng']))
					{
						$users_geo[$row['id']] = $geo;
					}
				}
			}

		// fetch messages

			$rs = $this->db->prepare('select m.id, m.content,
					m."Description" as description,
					m.msg_type, m.id_user,
					m.amount, m.units,
					u.name, u.letscode, u.postcode
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
				$row['url'] = $base_url . '/messages.php?id=' . $row['id'];
				$row['mail'] = $mailaddr[$uid] ?? '';
				$row['user'] = $row['letscode'] . ' ' . $row['name'];
				$row['user_url'] = $base_url . '/users.php?id=' . $uid;
				$row['addr'] = str_replace(' ', '+', $adr);
				$row['adr'] = $adr;

				if (isset($users_geo[$uid]))
				{
					$row['geo'] = $users_geo[$uid];
				}

				$messages[] = $row;
			}
		}

	// interSystem messages

		if (isset($block_options['interlets']) && $block_options['interlets'] == 'recent')
		{
			$eland_ary = $this->interlets_groups->get_eland($this->schema);

			foreach ($eland_ary as $sch => $d)
			{
				$interlets_msgs = [];

				$rs = $this->db->prepare('select m.id, m.content,
						m."Description" as description,
						m.msg_type, m.id_user,
						m.amount, m.units,
						u.name, u.letscode, u.postcode
					from ' . $sch . '.messages m, ' .
						$sch . '.users u
					where m.id_user = u.id
						and m.local = \'f\'
						and u.status IN (1, 2)
						and m.cdate >= ?
					order BY m.cdate DESC');

				$rs->bindValue(1, $treshold_time);
				$rs->execute();

				while ($row = $rs->fetch())
				{
					$row['type'] = $row['msg_type'] ? 'offer' : 'want';
					$row['offer'] = $row['type'] == 'offer' ? true : false;
					$row['want'] = $row['type'] == 'want' ? true : false;
					$row['user'] = $row['letscode'] . ' ' . $row['name'];

					$interlets_msgs[] = $row;
				}

				if (count($interlets_msgs))
				{
					$interlets[] = [
						'group'		=> $this->config->get('systemname', $sch),
						'messages'	=> $interlets_msgs,
					];
				}
			}

			$elas_ary = $this->interlets_groups->get_elas($this->schema);

			foreach ($elas_ary as $group_id => $ary)
			{
				$interlets_msgs = [];

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

					$interlets_msgs[] = $m;
				}

				if (count($interlets_msgs))
				{
					$interlets[] = [
						'group'		=> $ary['groupname'],
						'messages'	=> $interlets_msgs,
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

			$query = 'select n.*, u.name, u.letscode
				from ' . $this->schema . '.news n, ' .
					$this->schema . '.users u
				where n.approved = \'t\'
					and n.published = \'t\'
					and n.id_user = u.id ';

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

				$row['url'] = $base_url . '/news.php?id=' . $row['id'];
				$row['user'] = $row['letscode'] . ' ' . $row['name'];
				$row['user_url'] = $base_url . '/users.php?id=' . $row['id_user'];
				$row['itemdate_formatted'] = $this->date_format->get($row['itemdate'], 'day');

				$news[] = $row;
			}
		}

	// new users

		if (isset($block_options['new_users']))
		{

			$rs = $this->db->prepare('select u.id, u.name,
					u.letscode, u.postcode
				from ' . $this->schema . '.users u
				where u.status = 1
					and u.adate > ?');

			$time = gmdate('Y-m-d H:i:s', time() - $this->config->get('newuserdays', $this->schema) * 86400);
			$time = ($block_options['new_users'] === 'recent') ? $treshold_time: $time;

			$rs->bindValue(1, $time);
			$rs->execute();

			while ($row = $rs->fetch())
			{
				$row['url'] = $base_url . '/users.php?id=' . $row['id'];
				$row['text'] = $row['letscode'] . ' ' . $row['name'];

				$new_users[] = $row;
			}
		}

	// leaving users

		if (isset($block_options['leaving_users']))
		{
			$query = 'select u.id, u.name, u.letscode, u.postcode
				from ' . $this->schema . '.users u
				where u.status = 2';

			$query .= ($block_options['leaving_users'] == 'recent') ? ' and mdate > ?' : '';

			$rs = $this->db->prepare($query);

			if ($block_options['leaving_users'] === 'recent')
			{
				$rs->bindValue(1, $treshold_time);
			}

			$rs->execute();

			while ($row = $rs->fetch())
			{
				$row['url'] = $base_url . '/users.php?id=' . $row['id'];
				$row['text'] = $row['letscode'] . ' ' . $row['name'];

				$leaving_users[] = $row;
			}
		}

	// transactions

		if (isset($block_options['transactions']))
		{
			$rs = $this->db->prepare('select t.id_from, t.id_to,
					t.real_from, t.real_to,
					t.amount, t.cdate, t.description,
					uf.name as from_name, uf.letscode as from_letscode,
					ut.name as to_name, ut.letscode as to_letscode
				from ' . $this->schema . '.transactions t, ' .
					$this->schema . '.users uf, ' .
					$this->schema . '.users ut
				where t.id_from = uf.id
					and t.id_to = ut.id
					and t.cdate > ?');

			$rs->bindValue(1, $treshold_time);
			$rs->execute();

			while ($row = $rs->fetch())
			{
				$transactions[] = [
					'amount'	=> $row['amount'],
					'description'	=> $row['description'],
					'to_user'		=> $row['to_letscode'] . ' ' . $row['to_name'],
					'to_user_url'	=> $base_url . '/users.php?id=' . $row['id_to'],
					'from_user'		=> $row['from_letscode'] . ' ' . $row['from_name'],
					'from_user_url'	=> $base_url . '/users.php?id=' . $row['id_from'],
					'real_to'		=> $row['real_to'],
					'real_from'		=> $row['real_from'],
					'to_name'		=> $row['to_name'],
					'from_name'		=> $row['from_name'],
				];
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
						'url'		=> $base_url . '/forum.php?t=' . $row['eland_id'],
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
							'url'		=> $base_url . '/forum.php?t=' . $row['eland_id'],
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
						'url'			=> $this->s3_doc_url . $data['filename'],
						'ts'			=> $row['ts'],
					];

					$forum_topics[$row['eland_id']] = true;
				}
			}
		}

	//

		$vars = [
			'group'		=> [
				'name'				=> $this->config->get('systemname', $this->schema),
				'tag'				=> $this->config->get('systemtag', $this->schema),
				'currency'			=> $this->config->get('currency', $this->schema),
				'support'			=> explode(',', $this->config->get('support', $this->schema)),
				'saldofreqdays'		=> $this->config->get('saldofreqdays', $this->schema),
			],

			's3_img'				=> $this->s3_img_url,
			'new_users'				=> $new_users,
			'leaving_users'			=> $leaving_users,
			'news'					=> $news,
			'news_url'				=> $base_url . '/news.php?src=p',
			'transactions'			=> $transactions,
			'transactions_url'		=> $base_url . '/transactions.php?src=p',
			'new_transaction_url'	=> $base_url . '/transactions.php?add=1',
			'forum'					=> $forum,
			'forum_url'				=> $base_url . '/forum.php?src=p',
			'docs'					=> $docs,
			'docs_url'				=> $base_url . '/docs.php?src=p',
			'messages'				=> $messages,
			'messages_url'			=> $base_url . '/messages.php?src=p',
			'new_message_url'		=> $base_url . '/messages.php?add=1',
			'interlets'				=> $interlets,
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
				'validate_email'	=> true,
				'schema'	=> $this->schema,
				'to'		=> $to,
				'template'	=> 'periodic_overview',
				'vars'		=> array_merge($vars, [
					'user'			=> $users[$id],
					'url_login'		=> $base_url . '/login.php?login=' . $users[$id]['letscode'],
					'account_edit_url'	=> $base_url . '/users.php?edit=' . $id,
				]),
			], random_int(50, 500));

			$log_to[] = $users[$id]['letscode'] . ' ' . $users[$id]['name'] . ' (' . $id . ')';
		}

		$this->monolog->debug('x-saldomail, schema: ' .
			$this->schema . ' host:' . $host .
			' pid: ' . getmypid() . ' uid: ' .
			getmyuid() . ' inode: ' . getmyinode(),
			['schema' => $this->schema]);

		if (count($log_to))
		{
			$this->monolog->info('Saldomail queued, to: ' .
				implode(', ', $log_to), ['schema' => $this->schema]);
		}
		else
		{
			$this->monolog->info('mail: no saldomail queued',
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
