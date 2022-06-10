<?php declare(strict_types=1);

namespace App\Command\News;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class NewsViewsCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotNull(),
        new Choice(['admin', 'user', 'guest']),
    ])]
    #[ConfigMap(type: 'str', key: 'users.leaving.access')]
    public $map;
}
