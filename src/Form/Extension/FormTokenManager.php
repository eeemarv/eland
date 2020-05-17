<?php declare(strict_types=1);

namespace App\Form\Extension;

use App\Form\Extension\FormTokenManagerInterface;
use App\Service\TokenGeneratorService;
use Predis\Client as Predis;

class FormTokenManager implements FormTokenManagerInterface
{
    protected Predis $predis;
    protected TokenGeneratorService $token_generator_service;
    protected string $token;

    public function __construct(
        Predis $predis,
        TokenGeneratorService $token_generator_service
    )
    {
        $this->predis = $predis;
        $this->token_generator_service = $token_generator_service;
    }

    public function get():string
    {
        if (isset($this->token))
        {
            return $this->token;
        }

        $this->token = $this->token_generator_service->gen();

        $key = FormTokenManagerInterface::STORE_PREFIX . $this->token;
        $this->predis->set($key, '1');
        $this->predis->expire($key, FormTokenManagerInterface::TTL);

        return $this->token;
    }

    public function get_error_message(string $value):string
    {
        $key = FormTokenManagerInterface::STORE_PREFIX . $value;
        $count = $this->predis->incr($key);

        if ($count === 1)
        {
            $this->predis->del($key);
            return 'form.form_token.expired';
        }

        if ($count === 2)
        {
            return '';
        }

        return 'form.form_token.double';
    }
}
