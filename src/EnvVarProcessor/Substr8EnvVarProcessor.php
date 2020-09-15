<?php declare(strict_types=1);

namespace App\EnvVarProcessor;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class Substr8EnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv)
    {
        $env = $getEnv($name);
        return substr($env, 0, 8);
    }

    public static function getProvidedTypes():array
    {
        return [
            'substr8'   => 'string',
        ];
    }
}
