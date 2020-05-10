<?php declare(strict_types=1);

namespace App\Form\Extension;

interface EtokenManagerInterface
{
    public function get():string;
    public function getErrorMessage(string $value):string;
}
