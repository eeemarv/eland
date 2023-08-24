<?php declare(strict_types=1);

namespace App\EnvVarProcessor;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * See https://github.com/snc/SncRedisBundle/issues/709
 */
class TempRedisDnsFixEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): mixed
    {
        $env = $getEnv($name);

        return strtr($env, [
            'redis://:'     => 'redis://',
            'rediss://:'    => 'rediss://',
        ]);
    }

    public static function getProvidedTypes():array
    {
        return [
            'temp_redis_dns_fix'   => 'string',
        ];
    }
}
