<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsFilterCommand implements CommandInterface
{
    #[Type('string')]
    public $q;

    #[Type('int')]
    public $from_account;

    #[Choice(['and', 'or', 'nor'])]
    public $account_logic;

    #[Type('int')]
    public $to_account;

    #[Type('string')]
    public $from_date;

    #[Type('string')]
    public $to_date;

    #[Choice(choices:['srvc', 'stff', 'null'], multiple: true)]
    public $srvc;
}
