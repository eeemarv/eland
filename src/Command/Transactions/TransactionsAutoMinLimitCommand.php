<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsAutoMinLimitCommand implements CommandInterface
{
    #[Type('int')]
    #[NotNull()]
    #[ConfigMap(type: 'int', key: 'accounts.limits.auto_min.percentage')]
    public $percentage;

    #[Type('string')]
    #[ConfigMap(type: 'str', key: 'accounts.limits.auto_min.exclude.to')]
    public $exclude_to;

    #[Type('string')]
    #[ConfigMap(type: 'str', key: 'accounts.limits.auto_min.exclude.from')]
    public $exclude_from;
}
