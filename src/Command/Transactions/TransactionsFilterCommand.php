<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsFilterCommand implements CommandInterface
{
    #[Type(type: 'string')]
    public $q;

    #[Type(type: 'int')]
    public $from_account;

    #[Choice(choices: ['and', 'or', 'nor'])]
    public $account_logic;

    #[Type(type: 'int')]
    public $to_account;

    #[Type(type: 'string')]
    public $from_date;

    #[Type(type: 'string')]
    public $to_date;

    #[Choice(choices:['srvc', 'stff', 'null'], multiple: true)]
    public $srvc;
}
