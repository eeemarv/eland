<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsAddCommand implements CommandInterface
{
    #[Type(type: 'int')]
    #[Positive()]
    #[NotBlank()]
    public mixed $from_id;

    #[Type(type: 'string')]
    public mixed $from_remote_account;

    #[Type(type: 'int')]
    #[Positive()]
    #[NotBlank()]
    public mixed $to_id;

    #[Type(type: 'int')]
    #[Positive()]
    public mixed $to_remote_id;

    #[Type(type: 'string')]
    public mixed $to_remote_account;

    #[Type(type: 'int')]
    #[Positive()]
    #[NotBlank()]
    public mixed $amount;

    #[Type(type: 'int')]
    #[Positive()]
    public mixed $remote_amount;

    #[Type(type: 'string')]
    #[Length(min: 3, max: 60)]
    #[NotBlank()]
    public mixed $description;

    #[Choice(choices: ['service', 'stuff'])]
    public mixed $service_stuff;
}
