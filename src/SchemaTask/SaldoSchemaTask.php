<?php declare(strict_types=1);

namespace App\SchemaTask;

use App\HtmlProcess\HtmlToMarkdownConverter;
use Doctrine\DBAL\Connection as Db;
use App\Service\CacheService;
use Psr\Log\LoggerInterface;
use App\Queue\MailQueue;
use App\Service\IntersystemsService;
use App\Service\ConfigService;
use App\Service\MailAddrUserService;
use App\Render\AccountStrRender;

class SaldoSchemaTask implements SchemaTaskInterface
{
	protected Db $db;
	protected CacheService $cache_service;
	protected LoggerInterface $logger;
	protected MailQueue $mail_queue;
	protected IntersystemsService $intersystems_service;
	protected ConfigService $config_service;
	protected MailAddrUserService $mail_addr_user_service;
	protected AccountStrRender $account_str_render;
	protected HtmlToMarkdownConverter $html_to_markdown_converter;

	public function __construct(
		Db $db,
		CacheService $cache_service,
		LoggerInterface $logger,
		MailQueue $mail_queue,
		IntersystemsService $intersystems_service,
		ConfigService $config_service,
		MailAddrUserService $mail_addr_user_service,
		AccountStrRender $account_str_render,
		HtmlToMarkdownConverter $html_to_markdown_converter
	)
	{
		$this->db = $db;
		$this->cache_service = $cache_service;
		$this->logger = $logger;
		$this->mail_queue = $mail_queue;
		$this->intersystems_service = $intersystems_service;
		$this->config_service = $config_service;
		$this->mail_addr_user_service = $mail_addr_user_service;
		$this->account_str_render = $account_str_render;
		$this->html_to_markdown_converter = $html_to_markdown_converter;
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
		$mailaddr = $periodic_overvew_ary = [];

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
				u.name, u.balance, u.status,
				u.code, u.postcode, u.periodic_overview_en
			from ' . $schema . '.users u
			where u.status in (1, 2)');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$users[$row['id']] = $row;
		}

	// fetch mail addresses & periodic_overview_en

		$st = $this->db->prepare('select u.id, c.value, c.access
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

			if (!$users[$user_id] || !$users[$user_id]['periodic_overview_en'])
			{
				continue;
			}

			$periodic_overvew_ary[$user_id] = true;
		}

		if (isset($block_options['messages']))
		{

		// fetch addresses

			$addr = $addr_access = [];

			$rs = $this->db->prepare('select u.id, c.value, c.access
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
				$addr_access[$row['id']] = $row['access'];
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
				$adr = isset($addr_access[$uid]) && in_array($addr_access[$uid], ['user', 'guest']) ? $addr[$uid] : '';

				$image_file_ary = array_values(json_decode($row['image_files'] ?? '[]', true));
				$image_file = count($image_file_ary) ? $image_file_ary[0] : '';

				$row['description_plain_text'] = $this->html_to_markdown_converter->convert($row['description']);
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
						and m.access = \'guest\'
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
			$query = 'select n.*
				from ' . $schema . '.news n
				where n.approved = \'t\'
					and n.access in (\'user\', \'guest\', \'anonymous\') ';

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
				$row['newsitem_plain_text'] = $this->html_to_markdown_converter->convert($row['newsitem']);
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

		$forum = [];

		if (isset($block_options['forum']))
		{
			$all_visible_forum_topics = [];

			$stmt = $this->db->executeQuery('select *
				from ' . $schema . '.forum_topics
				where access in (\'user\', \'guest\', \'anonymous\')
				order by last_edit_at desc');

			$all_visible_forum_topics = $stmt->fetchAll();

			$new_replies = [];

			$rows = $this->db->executeQuery('select *
				from ' . $schema . '.forum_posts
				where created_at > ?',
				[$treshold_time]);

			foreach($rows as $row)
			{
				$new_replies[$row['topic_id']] = true;
			}

			foreach($all_visible_forum_topics as $forum_topic)
			{
				if ($forum_topic['created_at'] <= $treshold_time
					&& !isset($new_replies[$forum_topic['id']]))
				{
					continue;
				}

				$forum[] = $forum_topic;
			}
		}

	// docs

		if (isset($block_options['docs']))
		{
			$docs = $this->db->executeQuery('select
					coalesce(name, original_filename) as name,
					filename, created_at
				from ' . $schema . '.docs
				where access in (\'user\', \'guest\')
					and created_at > ?', [$treshold_time]);
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

		foreach ($periodic_overvew_ary as $id => $b)
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
			$this->logger->info('Periodic overview queued: ' .
				implode(', ', $log_to), ['schema' => $schema]);
		}
		else
		{
			$this->logger->info('Periodic overview NOT queued (no users)',
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
