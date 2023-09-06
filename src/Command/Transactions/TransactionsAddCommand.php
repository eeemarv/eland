<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsAddCommand implements CommandInterface
{
    #[Type('int')]
    #[Positive()]
    #[NotBlank()]
    public $from_id;

    #[Type('string')]
    public $from_remote_account;

    #[Type('int')]
    #[Positive()]
    #[NotBlank()]
    public $to_id;

    #[Type('int')]
    #[Positive()]
    public $to_remote_id;

    #[Type('string')]
    public $to_remote_account;

    #[Type('int')]
    #[Positive()]
    #[NotBlank()]
    public $amount;

    #[Type('int')]
    #[Positive()]
    public $remote_amount;

    #[Type('string')]
    #[Length(min: 3, max: 60)]
    #[NotBlank()]
    public $description;

    #[Choice(['service', 'stuff'])]
    public $service_stuff;
}
