<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\StaticContentRepository;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class StaticContentService
{
	const CACHE_PREFIX = 'static_content.';
	const CACHE_TTL = 864000; // 10 days
	const CACHE_BETA = 1;

	protected $local_cache = [];

	public function __construct(
		protected StaticContentRepository $static_content_repository,
		protected TagAwareCacheInterface $cache
	)
	{
	}

	public function clear_cache(null|string $schema):void
	{
		$this->local_cache = [];

		if (isset($schema))
		{
			$this->cache->invalidateTags(['static_content.' . $schema]);
		}
		else
		{
			$this->cache->invalidateTags(['static_content']);
		}
	}

	private function get_cache_key(
		string $lang,
		null|string $role,
		null|string $route,
		null|int $page_id,
		string $schema
	):string
	{
		$key = $schema;
		$key .= '.' . $lang;
		$key .= isset($role) ? '.' . $role : '.all_roles';
		$key .= isset($route) ? '.' . $route : '.all_routes';
		$key .= isset($page_id) ? '.' . $page_id : '';
		return $key;
	}

	public function set(
		null|string $role,
		null|string $route,
		null|int $page_id,
		string $block,
		string $content,
		SessionUserService $su,
		string $schema
	):void
	{
		$lang = 'nl';

		/**
		 * @depreciated empty string for $role and $route
		 * (should be null)
		 * */

		if ($role === '')
		{
			$role = null;
		}

		if ($route === '')
		{
			$route = null;
		}

		/**
		 * @depreciated _admin routes
		 * */

		if (isset($route) && substr($route, -6) === '_admin')
		{
			$route = substr($route, 0, strlen($route) - 6);
		}

		/** */

		$current_content = $this->get($role, $route, $page_id, $block, $schema);

		if ($current_content === $content)
		{
			return;
		}

		$key = $this->get_cache_key($lang, $role, $route, $page_id, $schema);

		$this->static_content_repository->set_content_block(
			$lang, $role, $route, $page_id, $block, $content, $su, $schema
		);

		unset($this->local_cache[$key]);
		$this->cache->delete(self::CACHE_PREFIX . $key);

		return;
	}

	public function get(
		null|string $role,
		null|string $route,
		null|int $page_id,
		string $block,
		string $schema
	):string
	{
		$lang = 'nl';

		/**
		 * @depreciated empty string for $role and $route
		 * (should be null)
		 * */

		 if ($role === '')
		 {
			 $role = null;
		 }

		 if ($route === '')
		 {
			 $route = null;
		 }

		 /**
		  * @depreciated _admin routes
		  * */

		if (isset($route) && substr($route, -6) === '_admin')
		{
			$route = substr($route, 0, strlen($route) - 6);
		}

		$key = $this->get_cache_key($lang, $role, $route, $page_id, $schema);

		if (isset($this->local_cache[$key]))
		{
			return $this->local_cache[$key][$block] ?? '';
		}

		$this->local_cache[$key] = $this->cache->get(self::CACHE_PREFIX . $key, function(ItemInterface $item) use ($schema, $key, $lang, $role, $route, $page_id){
			$item->tag(['static_content', 'static_content.' . $schema]);
			$item->expiresAfter(self::CACHE_TTL);
			return $this->static_content_repository->get_content_block_ary($lang, $role, $route, $page_id, $schema);
		}, self::CACHE_BETA);

		return $this->local_cache[$key][$block] ?? '';
	}
}
