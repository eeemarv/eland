<?php declare(strict_types=1);

namespace App\Form\Extension;

interface FormTokenManagerInterface
{
    const TTL = 28800; // 8 hours
    const NAME = 'form_token';
    const STORE_PREFIX = 'form_token_';
    const OPTION_ENABLED = 'form_token_enabled';
    const OPTION_PREVENT_DOUBLE = 'form_token_prevent_double';

    public function get():string;
    public function get_error_message(string $value, bool $prevent_double):null|string;
}
