<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Email;

class ConfigMailAddrCommand implements CommandInterface
{
    #[All(constraints: [
        new Email(),
    ])]
    #[ConfigMap(type: 'ary', key: 'mail.addresses.admin')]
    public $admin;

    #[All(constraints: [
        new Email(),
    ])]
    #[ConfigMap(type: 'ary', key: 'mail.addresses.support')]
    public $support;
}
