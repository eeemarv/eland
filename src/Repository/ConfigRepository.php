<?php declare(strict_types=1);

namespace App\Repository;

use App\Service\Xdb;
use Doctrine\DBAL\Connection as Db;
use Predis\Client as Redis;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ConfigRepository
{
	private $db;
	private $xdb;
	private $redis;
	private $localCache;
	private $isCli;

	private $default = [
		'preset_minlimit'					=> '',
		'preset_maxlimit'					=> '',
		'users_can_edit_username'			=> '0',
		'users_can_edit_fullname'			=> '0',
		'registration_en'					=> '0',
		'registration_top_text'				=> '',
		'registration_bottom_text'			=> '',
		'registration_success_text'			=> '',
		'registration_success_url'			=> '',
		'forum_en'							=> '0',
		'css'								=> '',
		'msgs_days_default'					=> '365',
		'balance_equilibrium'				=> '0',
		'date_format'						=> 'month_abbrev',
		'periodic_mail_block_ary' 			=> 
			'+messages.recent,interlets.recent,forum.recent,news.recent,docs.recent,transactions.recent',
		'default_landing_page'				=> 'messages',
		'homepage_url'						=> '',
		'template_lets'						=> '1',
		'interlets_en'						=> '1',
	];

	public function __construct(Db $db, Xdb $xdb, Redis $redis)
	{
		$this->redis = $redis;
		$this->db = $db;
		$this->xdb = $xdb;
		$this->isCli = php_sapi_name() === 'cli' ? true : false;
	}

	public function set(string $name, string $schema, string $value)
	{
		$this->xdb->set('setting', $name, $schema, ['value' => $value]);

		$this->redis->del($schema . '_config_' . $name);

		// prevent string too long error for eLAS database
		$value = substr($value, 0, 60);

		$this->db->update($schema . '.config', [
			'value' => $value, 
			'"default"' => 'f',
			], ['setting' => $name]);
	}

	public function get(string $key, string $schema):string
	{
		if (isset($this->localCache[$schema][$key]))
		{
			return $this->localCache[$schema][$key];
		}

		$redisKey = $schema . '_config_' . $key;

		if ($this->redis->exists($redisKey))
		{
			return $this->localCache[$schema][$key] = $this->redis->get($redisKey);
		}

		$row = $this->xdb->get('setting', $key, $schema);

		if ($row)
		{
			$value = $row['data']['value'];
		}
		else if (isset($this->default[$key]))
		{
			$value = $this->default[$key];
		}
		else
		{
			$value = $this->db->fetchColumn('select value 
				from ' . $schema . '.config 
				where setting = ?', [$key]);
			$this->xdb->set('setting', $key, $schema, ['value' => $value]);
		}

		if (!isset($value))
		{
			throw new NotFoundHttpException(sprintf(
				'Config %d does not exist in %s', 
				$key, __CLASS__));
        }

		$this->redis->set($redisKey, $value);
		$this->redis->expire($redisKey, 2592000);

		if (!$this->isCli)
		{
			$this->localCache[$schema][$key] = $value;
		}

		return $value;
	}
}

