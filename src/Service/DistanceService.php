<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use App\Service\CacheService;

class DistanceService
{
	protected $from_lat;
	protected $from_lng;
	protected $from_geo_en;
	protected $to_geo_en = false;

	protected $to_lat;
	protected $to_lng;
	protected $to_map_data = [];
	protected $dist;

	public function __construct(
		protected Db $db,
		protected CacheService $cache_service
	)
	{
	}

	public function set_from_geo(
		int $s_id,
		string $s_schema):self
	{
		if (isset($this->from_geo_en))
		{
			return $this;
		}

		if ($s_id && $s_schema)
		{
			$adr = $this->db->fetchOne('select c.value
				from ' . $s_schema . '.contact c, ' . $s_schema . '.type_contact tc
				where c.user_id = ?
					and c.id_type_contact = tc.id
					and tc.abbrev = \'adr\'',
				[$s_id], [\PDO::PARAM_STR]);
		}

		if (!$adr)
		{
			$this->from_geo_en = false;
			return $this;
		}

		$geo = $this->cache_service->get('geo_' . $adr);

		if (count($geo))
		{
			$this->from_lat = deg2rad($geo['lat']);
			$this->from_lng = deg2rad($geo['lng']);

			$this->from_geo_en = true;
			return $this;
		}

		$this->from_geo_en = false;

		return $this;
	}

	public function set_to_geo(string $adr):self
	{
		$adr = trim($adr);

		if (!$adr)
		{
			return $this;
		}

		$geo = $this->cache_service->get('geo_' . $adr);

		if (!count($geo))
		{
			$this->to_geo_en = false;
			return $this;
		}

		$this->to_map_data[] = [
			'lat'	=> $geo['lat'],
			'lng'	=> $geo['lng'],
		];

		$this->to_lat = deg2rad($geo['lat']);
		$this->to_lng = deg2rad($geo['lng']);

		$this->to_geo_en = true;
		return $this;
	}

	public function has_map_data():bool
	{
		return count($this->to_map_data) > 0;
	}

	public function get_map_markers():array
	{
		return $this->to_map_data;
	}

	public function calc():self
	{
		if (!isset($this->from_geo_en))
		{
			return $this;
		}

		if (!$this->from_geo_en)
		{
			return $this;
		}

		if (!$this->to_geo_en)
		{
			return $this;
		}

		$lat_d = $this->to_lat - $this->from_lat;
		$lng_d = $this->to_lng - $this->from_lng;

		$angle = 2 * asin(sqrt(pow(sin($lat_d / 2), 2) + cos($this->from_lat) * cos($this->to_lat) * pow(sin($lng_d / 2), 2)));

		$this->dist = 6371 * $angle;

		return $this;
	}

	public function format():string
	{
		if (!isset($this->from_geo_en))
		{
			return '';
		}

		if (!$this->from_geo_en)
		{
			return '';
		}

		if (!$this->to_geo_en)
		{
			return '';
		}

		if ($this->dist < 1)
		{
			return (round($this->dist * 10) * 100) . ' m';
		}

		if ($this->dist < 10)
		{
			return (round($this->dist * 10) / 10) . ' km';
		}

		return round($this->dist) . ' km';
	}

	public function format_parenthesis():string
	{
		$p = $this->format();

		return $p ? ' (' . $p . ')' : '';
	}
}
