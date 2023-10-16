<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;

class MessagesSubjectCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new Length(min: 3, max: 200),
    ])]
    public $subject;

    #[Sequentially([
        new NotNull(),
        new Choice(['offer', 'want'])
    ])]
    public $offer_want;
}
