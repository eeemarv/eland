<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsCurrencyCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotBlank(),
        new Type(type: 'string'),
        new Length(min: 1, max: 40),
    ])]
    #[ConfigMap(type: 'str', key: 'transactions.currency.name')]
    public $currency;

    #[Type(type: 'bool')]
    #[ConfigMap(type: 'bool', key: 'transactions.currency.timebased_en')]
    public $timebased_en;

    #[Sequentially(constraints: [
        new NotBlank(),
        new Type('int'),
        new Range(min: 1, max: 3600),
    ])]
    #[ConfigMap(type: 'int', key: 'transactions.currency.per_hour_ratio')]
    public $per_hour_ratio;
}
