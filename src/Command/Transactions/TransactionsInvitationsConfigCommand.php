<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Attributes\FeaturesMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsInvitationsConfigCommand implements CommandInterface
{
    #[Type(type: 'int')]
    #[Positive()]
    #[NotBlank()]
    #[FeaturesMap(type: 'int', key: 'transactions.invitations.days')]
    public mixed $days;
}
