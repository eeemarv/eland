<?php declare(strict_types=1);

namespace App\Form\Extension;

interface FormTokenManagerInterface
{
    const TTL = 14400; // 4 hours
    const NAME = 'form_token';
    const STORE_PREFIX = 'form_token_';
    const FORM_OPTION = 'form_token_enabled';

    public function get():string;
    public function get_error_message(string $value):string;
}
