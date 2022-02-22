<?php declare(strict_types=1);

namespace App\Command\Config;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class ConfigMailCommand implements CommandInterface
{
    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'mail.enabled')]
    public $enabled;

    #[Sequentially([
        new NotBlank(),
        new Length(max: 20),
    ])]
    #[ConfigMap(type: 'str', key: 'mail.tag')]
    public $tag;
}
