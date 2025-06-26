<?php declare(strict_types=1);

namespace App\Email\Index\ContactSuccess;

use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Mime\Address;

#[AsMessage('mail_hi')]
final class EmailIndexContactSuccessMessage
{
    public function __construct(
        public readonly Address $to,
        public readonly string $message,
    )
    {
    }
}