<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\RuntimeExtensionInterface;

class FlattenArrayRuntime implements RuntimeExtensionInterface
{
	public function __construct(
	)
	{
	}

	public function get_flatten_array(array $params):array
	{
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
