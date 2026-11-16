<?php declare(strict_types=1);

namespace App\Command\Transactions;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

class TransactionsInvitationsAddCommand implements CommandInterface
{
    #[Type(type: 'int')]
    #[Positive()]
    #[NotBlank()]
    public mixed $from_id;

    #[Email()]
    #[NotBlank()]    
    public mixed $to_email;

    #[Type(type: 'int')]
    #[Positive()]
    #[NotBlank()]
    public mixed $amount;

    #[Type(type: 'string')]
    #[Length(min: 3, max: 60)]
    #[NotBlank()]
    public mixed $description;

    #[Choice(choices: ['service', 'stuff'])]
    public mixed $service_stuff;

    #[Type(type: 'string')]
    public mixed $invitation_text;
}
