<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class ConfigMailAddrCommand implements CommandInterface
{
    #[ConfigMap(type: 'ary', key: 'mail.addresses.admin')]
    public $admin;

    #[ConfigMap(type: 'ary', key: 'mail.addresses.support')]
    public $support;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('admin', new All([
            'constraints' => [
                new Email(),
            ],
        ]));

        $metadata->addPropertyConstraint('support', new All([
            'constraints' => [
                new Email(),
            ],
        ]));
    }
}
