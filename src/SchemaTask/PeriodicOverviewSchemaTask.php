<?php declare(strict_types=1);

namespace App\SchemaTask;

use App\Cache\ConfigCache;
use App\HtmlProcess\HtmlToMarkdownConverter;
use Doctrine\DBAL\Connection as Db;
use App\Service\CacheService;
use Psr\Log\LoggerInterface;
use App\Queue\MailQueue;
use App\Service\IntersystemsService;
use App\Service\MailAddrUserService;
use App\Render\AccountStrRender;
use Doctrine\DBAL\Types\Types;

class PeriodicOverviewSchemaTask implements SchemaTaskInterface
{
	public function __construct(
		protected Db $db,
		protected CacheService $cache_service,
		protected LoggerInterface $logger,
		protected MailQueue $mail_queue,
		protected IntersystemsService $intersystems_service,
		protected ConfigCache $config_cache,
		protected MailAddrUserService $mail_addr_user_service,
		protected AccountStrRender $account_str_render,
		protected HtmlToMarkdownConverter $html_to_markdown_converter
	)
	{
	}

	public static function get_default_index_name():string
	{
		return 'saldo';
	}

	public function run(string $schema, bool $update):void
	{
        $mollie_enabled = $this->config_cache->get_bool('mollie.enabled', $schema);
        $messages_enabled = $this->config_cache->get_bool('messages.enabled', $schema);
        $transactions_enabled = $this->config_cache->get_bool('transactions.enabled', $schema);
        $news_enabled = $this->config_cache->get_bool('news.enabled', $schema);
        $docs_enabled = $this->config_cache->get_bool('docs.enabled', $schema);
		$forum_enabled = $this->config_cache->get_bool('forum.enabled', $schema);
		$new_users_enabled = $this->config_cache->get_bool('users.new.enabled', $schema);
		$leaving_users_enabled = $this->config_cache->get_bool('users.leaving.enabled', $schema);

        $postcode_enabled = $this->config_cache->get_bool('users.fields.postcode.enabled', $schema);

		$intersystem_en = $this->config_cache->get_intersystem_en($schema);

		$now_unix = time();
		$days = $this->config_cache->get_int('periodic_mail.days', $schema);
		$treshold_time_unix = $now_unix - ($days * 86400);
		$treshold_time =\DateTimeImmutable::createFromFormat('U', (string) $treshold_time_unix);
		$new_user_treshold = $this->config_cache->get_new_user_treshold($schema);
		$expires_at_enabled = $this->config_cache->get_bool('messages.fields.expires_at.enabled', $schema);

		$users = $news = $new_users = [];
		$leaving_users = $transactions = $messages = [];
		$forum = $intersystem = $docs = [];
		$mailaddr = $periodic_overview_ary = [];

	// get blocks

		$block_options = [];

		$block_ary = $this->config_cache->get_ary('periodic_mail.user.layout', $schema);

		foreach ($block_ary as $block)
		{
			$select = 'recent';

			if (in_array($block, ['news', 'new_users', 'leaving_users']))
			{
				$select = $this->config_cache->get_str('periodic_mail.user.render.' . $block . '.select', $schema);
				$select = $select === 'all' ? 'all' : 'recent';
			}

			if (in_array($block, ['messages_self', 'mollie']))
			{
				$select = 'all';
			}

			$block_options[$block] = $select;
		}

        if (!$mollie_enabled)
        {
            unset($block_options['mollie']);
        }

        if (!$forum_enabled)
        {
            unset($block_options['forum']);
        }

        if (!$transactions_enabled)
        {
            unset($block_options['transactions']);
        }

        if (!$messages_enabled)
        {
            unset($block_options['messages']);
            unset($block_options['messages_self']);
            unset($block_options['intersystem']);
        }

        if (!$news_enabled)
        {
            unset($block_options['news']);
        }

        if (!$docs_enabled)
        {
            unset($block_options['docs']);
        }

        if (!$intersystem_en)
        {
            unset($block_options['intersystem']);
		}

		if (!$new_users_enabled)
		{
			unset($block_options['new_users']);
		}

		if (!$leaving_users_enabled)
		{
			unset($block_options['leaving_users']);
		}

		$blocks_sorted = array_keys($block_options);

	// fetch all active users

		$stmt = $this->db->prepare('select u.id,
				u.name, u.status,
				u.code, u.postcode,
				u.periodic_overview_en
			from ' . $schema . '.users u
			where u.is_active
				and u.remote_schema is null
				and u.remote_email is null)');

		$res = $stmt->executeQuery();

		while ($row = $res->fetchAssociative())
		{
			$users[$row['id']] = $row;
		}

	// fetch mail addresses & periodic_overview_en

		$stmt = $this->db->prepare('select u.id, c.value, c.access
			from ' . $schema . '.users u, ' .
				$schema . '.contact c, ' .
				$schema . '.type_contact tc
			where u.is_active
				and u.remote_schema is null
				and u.remote_email is null
				and u.id = c.user_id
				and c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'');

		$res = $stmt->executeQuery();

		while ($row = $res->fetchAssociative())
		{
			$user_id = $row['id'];
			$mail = $row['value'];
			$mailaddr[$user_id][] = $mail;

			if (!isset($users[$user_id]) || !$users[$user_id]['periodic_overview_en'])
			{
				continue;
			}

			$periodic_overview_ary[$user_id] = true;
		}

		if (isset($block_options['messages']))
		{

		// fetch addresses

			$addr = $addr_access = [];

			$stmt = $this->db->prepare('select u.id, c.value, c.access
				from ' . $schema . '.users u, ' .
					$schema . '.contact c, ' .
					$schema . '.type_contact tc
				where u.is_active
					and u.remote_schema is null
					and u.remote_email is null
					and u.id = c.user_id
					and c.id_type_contact = tc.id
					and tc.abbrev = \'adr\'');

			$res = $stmt->executeQuery();

			while ($row = $res->fetchAssociative())
			{
				$addr[$row['id']] = $row['value'];
				$addr_access[$row['id']] = $row['access'];
				$users[$row['id']]['adr'] = $row['value'];
			}

		// fetch messages

			$stmt = $this->db->prepare('select m.id,
					m.subject, m.content,
					m.user_id,
					m.offer_want,
					m.image_files
				from ' . $schema . '.messages m, ' .
					$schema . '.users u
				where m.user_id = u.id
					and u.is_active
					and u.remote_schema is null
					and u.remote_email is null
					and m.created_at >= ?
					and ((m.expires_at >= timezone(\'utc\', now())
						or m.expires_at is null) or not ?)
				order by m.created_at desc');

			$stmt->bindValue(1, $treshold_time, Types::DATETIME_IMMUTABLE);
			$stmt->bindValue(2, $expires_at_enabled, \PDO::PARAM_BOOL);
			$res = $stmt->executeQuery();

			while ($row = $res->fetchAssociative())
			{
				$uid = $row['user_id'];
				$adr = isset($addr_access[$uid]) && in_array($addr_access[$uid], ['user', 'guest']) ? $addr[$uid] : '';

				$image_file_ary = array_values(json_decode($row['image_files'] ?? '[]', true));
				$image_file = count($image_file_ary) ? $image_file_ary[0] : '';

				$row['content_plain_text'] = $this->html_to_markdown_converter->convert($row['content']);
				$row['image_file'] = $image_file;
				$row['mail'] = $mailaddr[$uid] ?? '';
				$row['addr'] = str_replace(' ', '+', $adr);
				$row['adr'] = $adr;

				if ($postcode_enabled)
				{
					$row['postcode'] = $users[$uid]['postcode'];
				}

				$messages[] = $row;
			}
		}

	// interSystem messages

		if (isset($block_options['intersystem']))
		{
			$eland_ary = $this->intersystems_service->get_eland($schema);

			foreach ($eland_ary as $sch => $d)
			{
				$intersystem_postcode_enabled = $this->config_cache->get_bool('users.fields.postcode.enabled', $sch);

				if (!$this->config_cache->get_bool('messages.enabled', $sch))
				{
					continue;
				}

				$intersystem_msgs = [];

				$expires_at_enabled_intersystem = $this->config_cache->get_bool('messages.fields.expires_at.enabled', $sch);

				$stmt = $this->db->prepare('select m.id, m.subject,
						m.content,
						m.offer_want,
						m.user_id as user_id,
						u.postcode
					from ' . $sch . '.messages m, ' .
						$sch . '.users u
					where m.user_id = u.id
						and m.access = \'guest\'
						and u.is_active
						and u.remote_schema is null
						and u.remote_email is null
						and m.created_at >= ?
						and ((m.expires_at >= timezone(\'utc\', now())
							or m.expires_at is null) or not ?)
					order by m.created_at desc');

				$stmt->bindValue(1, $treshold_time, Types::DATETIME_IMMUTABLE);
				$stmt->bindValue(2, $expires_at_enabled_intersystem, \PDO::PARAM_BOOL);
				$res = $stmt->executeQuery();

				while ($row = $res->fetchAssociative())
				{
					if (!$intersystem_postcode_enabled)
					{
						unset($row['postcode']);
					}

					$intersystem_msgs[] = $row;
				}

				$intersystem[] = [
					'eland_server'	=> true,
					'elas'			=> false,
					'schema'		=> $sch,
					'messages'		=> $intersystem_msgs,
				];
			}

			if (!count($eland_ary))
			{
				unset($block_options['intersystem']);
			}
		}

	// news

		if (isset($block_options['news']))
		{
			$query = 'select n.*
				from ' . $schema . '.news n
				where n.access in (\'user\', \'guest\', \'anonymous\') ';

			$query .= $block_options['news'] == 'recent' ? 'and n.created_at > ? ' : '';
			$query .= 'order by ';
			$query .= $this->config_cache->get_bool('news.sort.asc', $schema) ? 'n.event_at asc, ' : '';
			$query .= 'n.created_at desc';

			$stmt = $this->db->prepare($query);

			if ($block_options['news'] == 'recent')
			{
				$stmt->bindValue(1, $treshold_time, Types::DATETIME_IMMUTABLE);
			}

			$res = $stmt->executeQuery();

			while ($row = $res->fetchAssociative())
			{
				$row['content_plain_text'] = $this->html_to_markdown_converter->convert($row['content']);
				$news[] = $row;
			}
		}

	// new users

		if (isset($block_options['new_users']))
		{

			$stmt = $this->db->prepare('select u.id
				from ' . $schema . '.users u
				where u.is_active
					and not u.is_leaving
					and u.remote_schema is null
					and u.remote_email is null
					and u.activated_at > ?');

			$time = ($block_options['new_users'] === 'recent') ? $treshold_time : $new_user_treshold;

			$stmt->bindValue(1, $time, Types::DATETIME_IMMUTABLE);
			$res = $stmt->executeQuery();

			while ($row = $res->fetchAssociative())
			{
				$new_users[] = $row['id'];
			}
		}

	// leaving users

		if (isset($block_options['leaving_users']))
		{
			$query = 'select u.id
				from ' . $schema . '.users u
				where u.is_active
					and u.is_leaving
					and u.remote_schema is null
					and u.remote_email is null';

			if ($block_options['leaving_users'] === 'recent')
			{
				$query .= ' and last_edit_at > ?';
			}

			$stmt = $this->db->prepare($query);

			if ($block_options['leaving_users'] === 'recent')
			{
				$stmt->bindValue(1, $treshold_time, Types::DATETIME_IMMUTABLE);
			}

			$res = $stmt->executeQuery();

			while ($row = $res->fetchAssociative())
			{
				$leaving_users[] = $row['id'];
			}
		}

	// transactions

		if (isset($block_options['transactions']))
		{
			$stmt = $this->db->prepare('select t.*
				from ' . $schema . '.transactions t
				where t.created_at > ?');

			$stmt->bindValue(1, $treshold_time, Types::DATETIME_IMMUTABLE);
			$res = $stmt->executeQuery();

			while ($row = $res->fetchAssociative())
			{
				$transactions[] = $row;
			}
		}

	// forum

		$forum = [];

		if (isset($block_options['forum']))
		{
			$all_visible_forum_topics = [];

			$stmt = $this->db->prepare('select *
				from ' . $schema . '.forum_topics
				where access in (\'user\', \'guest\', \'anonymous\')
				order by last_edit_at desc');

			$res = $stmt->executeQuery();

			while ($row = $res->fetchAssociative())
			{
				$all_visible_forum_topics[] = $row;
			}

			$new_replies = [];

			$stmt = $this->db->prepare('select *
				from ' . $schema . '.forum_posts
				where created_at > ?');

			$stmt->bindValue(1, $treshold_time, Types::DATETIME_IMMUTABLE);

			$res = $stmt->executeQuery();

			while ($row = $res->fetchAssociative())
			{
				$new_replies[$row['topic_id']] = true;
			}

			foreach ($all_visible_forum_topics as $forum_topic)
			{
				$created_at = \DateTimeImmutable::createFromFormat('U', (string) strtotime($forum_topic['created_at'] . ' UTC'));

				if ($created_at->getTimestamp() <= $treshold_time->getTimestamp()
					&& !isset($new_replies[$forum_topic['id']]))
				{
					continue;
				}

				$forum[] = $forum_topic;
			}
		}

	// docs

		$docs = [];

		if (isset($block_options['docs']))
		{
			$stmt = $this->db->prepare('select
					coalesce(name, original_filename) as name,
					filename, created_at
				from ' . $schema . '.docs
				where access in (\'user\', \'guest\')
					and created_at > ?');

			$stmt->bindValue(1, $treshold_time, Types::DATETIME_IMMUTABLE);

			$res = $stmt->executeQuery();

			while ($row = $res->fetchAssociative())
			{
				$docs[] = $row;
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

		$this->logger->debug('#periodic mail vars ' . json_encode($vars), ['schema' => $schema]);

	// queue mail

		$log_to = [];

		foreach ($periodic_overview_ary as $user_id => $b)
		{
			$vars['user_id'] = $user_id;

			// messages_self

			$vars['messages_self'] = [];

			if (isset($block_options['messages_self']))
			{
				$stmt = $this->db->prepare('select m.id,
						m.subject, m.offer_want,
						extract(epoch from coalesce(m.expires_at, now()))::int as expires_at_unix,
						m.expires_at, m.created_at
					from ' . $schema . '.messages m
					where m.user_id = ?
					order by m.created_at desc');

				$stmt->bindValue(1, $user_id, \PDO::PARAM_INT);

				$res = $stmt->executeQuery();

				while ($row = $res->fetchAssociative())
				{
					$row['is_expired'] = $expires_at_enabled && isset($row['expires_at']) && ($row['expires_at_unix'] < $now_unix);
					$vars['messages_self'][] = $row;
				}
			}

			// mollie

			$vars['mollie'] = [];

			if ($mollie_enabled)
			{
				$stmt = $this->db->prepare('select p.amount, p.token, r.description
					from ' . $schema . '.mollie_payments p,
						' . $schema . '.mollie_payment_requests r
					where p.request_id = r.id
						and user_id = ?
						and is_canceled = \'f\'::bool
						and is_paid = \'f\'::bool');

				$stmt->bindValue(1, $user_id, \PDO::PARAM_INT);
				$res = $stmt->executeQuery();

				while ($row = $res->fetchAssociative())
				{
					$vars['mollie'][] = $row;
				}
			}

			//

			$to = $this->mail_addr_user_service->get_active($user_id, $schema);

			if (!count($to))
			{
				$this->logger->info('No periodic mail queued for user ' .
				$this->account_str_render->get_with_id($user_id, $schema) . ' because no email address.',
				['schema' => $schema]);

				continue;
			}

			$this->mail_queue->queue([
				'schema'			=> $schema,
				'to'				=> $to,
				'template'			=> 'periodic_overview/periodic_overview',
				'vars'				=> $vars,
			], random_int(0, 5000));

			$log_str = $this->account_str_render->get_with_id($user_id, $schema);
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
		return $this->config_cache->get_int('periodic_mail.days', $schema) > 0
			&& $this->config_cache->get_bool('periodic_mail.enabled', $schema);
	}

	public function get_interval(string $schema):int
	{
		$days = $this->config_cache->get_int('periodic_mail.days', $schema);
		$days = $days < 1 ? 7 : $days;

		return 86400 * $days;
	}
}
