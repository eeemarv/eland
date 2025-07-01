<?php declare(strict_types=1);

namespace App\Email\ContactForm\ContactConfirm;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Mime\Address;

#[AsMessage('mail_hi')]
final class EmailContactConfirmMessage
{
    public function __construct(
        public readonly Address $to,
        public readonly string $token,
        public readonly Schema $schema,
    )
    {
    }
}