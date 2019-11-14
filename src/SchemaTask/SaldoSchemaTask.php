<?php declare(strict_types=1);

namespace App\SchemaTask;

use Doctrine\DBAL\Connection as Db;
use App\Service\XdbService;
use App\Service\CacheService;
use Psr\Log\LoggerInterface;
use App\Queue\MailQueue;
use App\Service\IntersystemsService;
use App\Service\ConfigService;
use App\Service\MailAddrUserService;
use App\Render\AccountStrRender;

class SaldoSchemaTask implements SchemaTaskInterface
{
	protected $db;
	protected $xdb_service;
	protected $cache_service;
	protected $logger;
	protected $mail_queue;
	protected $intersystems_service;
	protected $config_service;
	protected $mail_addr_user_service;
	protected $account_str_render;

	public function __construct(
		Db $db,
		XdbService $xdb_service,
		CacheService $cache_service,
		LoggerInterface $logger,
		MailQueue $mail_queue,
		IntersystemsService $intersystems_service,
		ConfigService $config_service,
		MailAddrUserService $mail_addr_user_service,
		AccountStrRender $account_str_render
	)
	{
		$this->db = $db;
		$this->xdb_service = $xdb_service;
		$this->cache_service = $cache_service;
		$this->logger = $logger;
		$this->mail_queue = $mail_queue;
		$this->intersystems_service = $intersystems_service;
		$this->config_service = $config_service;
		$this->mail_addr_user_service = $mail_addr_user_service;
		$this->account_str_render = $account_str_render;
	}

	public static function get_default_index_name():string
	{
		return 'saldo';
	}

	public function run(string $schema, bool $update):void
	{
		$treshold_time = gmdate('Y-m-d H:i:s', time() - $this->config_service->get('saldofreqdays', $schema) * 86400);

		$users = $news = $new_users = [];
		$leaving_users = $transactions = $messages = [];
		$forum = $intersystem = $docs = [];
		$mailaddr = $saldo_mail = [];

	// get blocks

		$forum_en = $this->config_service->get('forum_en', $schema) ? true : false;
		$intersystem_en = $this->config_service->get('interlets_en', $schema) ? true : false;
		$intersystem_en = $intersystem_en && $this->config_service->get('template_lets', $schema) ? true : false;

		$blocks_sorted = $block_options = [];

		$block_ary = $this->config_service->get('periodic_mail_block_ary', $schema);

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
			from ' . $schema . '.users u
			where u.status in (1, 2)');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$users[$row['id']] = $row;
		}

	// fetch mail addresses & cron_saldo

		$st = $this->db->prepare('select u.id, c.value, c.flag_public
			from ' . $schema . '.users u, ' .
				$schema . '.contact c, ' .
				$schema . '.type_contact tc
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

			if (!$users[$user_id] || !$users[$user_id]['cron_saldo'])
			{
				continue;
			}

			$saldo_mail[$user_id] = true;
		}

		if (isset($block_options['messages']))
		{

		// fetch addresses

			$addr = $addr_public = $addr_p = [];

			$rs = $this->db->prepare('select u.id, c.value, c.flag_public
				from ' . $schema . '.users u, ' .
					$schema . '.contact c, ' .
					$schema . '.type_contact tc
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
					m.amount, m.units, m.image_files
				from ' . $schema . '.messages m, ' .
					$schema . '.users u
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

				$image_file_ary = array_values(json_decode($row['image_files'] ?? '[]', true));
				$image_file = count($image_file_ary) ? $image_file_ary[0] : '';

				$row['type'] = $row['msg_type'] ? 'offer' : 'want';
				$row['offer'] = $row['type'] == 'offer' ? true : false;
				$row['want'] = $row['type'] == 'want' ? true : false;
				$row['image_file'] = $image_file;
				$row['mail'] = $mailaddr[$uid] ?? '';
				$row['addr'] = str_replace(' ', '+', $adr);
				$row['adr'] = $adr;

				$messages[] = $row;
			}
		}

		error_log(json_encode($block_options));

	// interSystem messages

		if (isset($block_options['interlets']) && $block_options['interlets'] == 'recent')
		{
			$eland_ary = $this->intersystems_service->get_eland($schema);

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
		}

	// news

		if (isset($block_options['news']))
		{
			$rows = $this->xdb_service->get_many([
				'agg_schema' => $schema,
				'agg_type' => 'news_access',
			]);

			foreach ($rows as $row)
			{
				$news_access_ary[$row['eland_id']] = $row['data']['access'];
			}

			$query = 'select n.*
				from ' . $schema . '.news n
				where n.approved = \'t\' ';

			$query .= $block_options['news'] == 'recent' ? 'and n.cdate > ? ' : '';
			$query .= 'order by n.itemdate ';
			$query .= $this->config_service->get('news_order_asc', $schema) === '1' ? 'asc' : 'desc';

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
					$this->xdb_service->set('news_access',
						(string) $row['id'],
						['access' => 'interlets'],
						$schema);

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
				from ' . $schema . '.users u
				where u.status = 1
					and u.adate > ?');

			$time = gmdate('Y-m-d H:i:s', time() - $this->config_service->get('newuserdays', $schema) * 86400);
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
				from ' . $schema . '.users u
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
				from ' . $schema . '.transactions t
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

			$rows = $this->xdb_service->get_many([
				'agg_schema' => $schema,
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

			$rows = $this->xdb_service->get_many(['agg_schema' => $schema,
				'agg_type' => 'forum',
				'data->>\'parent_id\'' => ['is not null'],
				'ts' => ['>' => $treshold_time]], 'order by event_time desc');

			foreach ($rows as $row)
			{
				$data = $row['data'];

				if (!isset($forum_topics[$data['parent_id']]))
				{
					$forum_topics_replied[] = $schema . '_forum_' . $data['parent_id'];
				}
			}

			if (count($forum_topics_replied))
			{
				$rows = $this->xdb_service->get_many(['agg_id_ary' => $forum_topics_replied,
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
			$rows = $this->xdb_service->get_many(['agg_schema' => $schema,
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
			$to = $this->mail_addr_user_service->get_active($id, $schema);

			if (!count($to))
			{
				$this->logger->info('No periodic mail queued for user ' .
				$this->account_str_render->get_with_id($id, $schema) . ' because no email address.',
				['schema' => $schema]);

				continue;
			}

			$this->mail_queue->queue([
				'schema'			=> $schema,
				'to'				=> $to,
				'template'			=> 'periodic_overview/periodic_overview',
				'vars'				=> array_merge($vars, [
					'user_id'		=> $id,
				]),
			], random_int(0, 5000));

			$log_str = $this->account_str_render->get_with_id($id, $schema);
			$log_str .= ' to: ' . json_encode($to) . ' )';
			$log_to[] = $log_str;
		}

		if (count($log_to))
		{
			$this->logger->info('Saldomail queued: ' .
				implode(', ', $log_to), ['schema' => $schema]);
		}
		else
		{
			$this->logger->info('Saldomail NOT queued (no users)',
				['schema' => $schema]);
		}

		return;
	}

	public function is_enabled(string $schema):bool
	{
		return $this->config_service->get('saldofreqdays', $schema) ? true : false;
	}

	public function get_interval(string $schema):int
	{
		if (isset($schema))
		{
			$days = $this->config_service->get('saldofreqdays', $schema);
			$days = $days < 1 ? 7 : $days;

			return 86400 * $days;
		}

		return 86400;
	}
}
