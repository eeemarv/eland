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
    #[Type('int')]
    #[Positive()]
    #[NotBlank()]
    public mixed $from_id;

    #[Type('string')]
    public mixed $from_remote_account;

    #[Type('int')]
    #[Positive()]
    #[NotBlank()]
    public mixed $to_id;

    #[Type('int')]
    #[Positive()]
    public mixed $to_remote_id;

    #[Type('string')]
    public mixed $to_remote_account;

    #[Type('int')]
    #[Positive()]
    #[NotBlank()]
    public mixed $amount;

    #[Type('int')]
    #[Positive()]
    public mixed $remote_amount;

    #[Type('string')]
    #[Length(min: 3, max: 60)]
    #[NotBlank()]
    public mixed $description;

    #[Choice(['service', 'stuff'])]
    public mixed $service_stuff;
}
