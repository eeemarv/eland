<?php declare(strict_types=1);

namespace util;

class distance
{
	public function __construct()
	{
	}

	public static function get(array $from, array $to)
	{
		$to_lat = deg2rad($to['lat']);
		$to_lng = deg2rad($to['lng']);
		$from_lat = deg2rad($from['lat']);
		$from_lng = deg2rad($from['lng']);

		$lat_d = $to_lat - $from_lat;
		$lng_d = $to_lng - $from_lng;

		$angle = 2 * asin(sqrt(pow(sin($lat_d / 2), 2) + cos($from_lat) * cos($to_lat) * pow(sin($lng_d / 2), 2)));

		return 6371 * $angle;
	}

	public static function format(array $from, array $to)
	{
		$d = self::get($from, $to);

		if ($d < 1)
		{
			return (round($d * 10) * 100) . ' m';
		}

		if ($d < 10)
		{
			return (round($d * 10) / 10) . ' km';
		}

		return round($d) . ' km';
	}

	public static function format_p(array $from, array $to)
	{
		$p = self::format($from, $to);

		return $p ? ' (' . $p . ')' : '';
	}
}
