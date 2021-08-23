<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\RuntimeExtensionInterface;

class FlattenRuntime implements RuntimeExtensionInterface
{
	public function get_flatten(array $params):array
	{
        if (!$params)
        {
            return [];
        }

		$out_ary = [];

        $params = http_build_query($params, 'prefix', '&');
        $params = urldecode($params);
        $params = explode('&', $params);

        foreach ($params as $param)
        {
            [$name, $value] = explode('=', $param);

            if (!isset($value) || $value === '')
            {
                continue;
            }

			$out_ary[$name] = $value;
        }

		return $out_ary;
	}
}
