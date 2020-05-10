<?php declare(strict_types=1);

namespace App\Form\Extension;

use App\Form\Extension\EtokenManagerInterface;

use Predis\Client as Redis;

class EtokenManager implements EtokenManagerInterface
{
    private $ttl = 14400;
    private $bytes = 15;
    private $prefix = 'etoken_';
    private $redis;
    private $value;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function get():string
    {
        if (isset($this->value))
        {
            return $this->value;
        }

        $this->value = base64_encode(random_bytes($this->bytes));
        $key = $this->prefix . $this->value;
        $this->redis->set($key, '1');
        $this->redis->expire($key, $this->ttl);
        return $this->value;
    }

    public function getErrorMessage(string $value):string
    {
        $key = $this->prefix . $value;
        $count = $this->redis->incr($key);

        if ($count === 1)
        {
            $this->redis->del($key);
            return 'form.etoken.expired';
        }

        if ($count === 2)
        {
            return '';
        }       

        return 'form.etoken.double';
    }
}
