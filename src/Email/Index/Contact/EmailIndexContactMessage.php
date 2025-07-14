<?php declare(strict_types=1);

namespace App\Email\Index\Contact;

use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Mime\Address;

#[AsMessage('mail_lo')]
final class EmailIndexContactMessage
{
  public function __construct(
    public readonly Address $reply_to,
    public readonly string $message,
    public readonly string $agent,
    public readonly string $ip,
  )
  {
  }
}