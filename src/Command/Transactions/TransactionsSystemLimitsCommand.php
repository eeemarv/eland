<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsSystemLimitsCommand implements CommandInterface
{
    #[Type(type: 'int')]
    #[ConfigMap(type: 'int', key: 'accounts.limits.global.min')]
    public $min;

    #[Type(type: 'int')]
    #[ConfigMap(type: 'int', key: 'accounts.limits.global.max')]
    public $max;
}
