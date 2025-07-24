<?php declare(strict_types=1);

namespace App\Email\RegisterForm\Confirm;

use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Mime\Address;

#[AsMessage('mail_hi')]
final class EmailRegisterFormConfirmMessage
{
  public function __construct(
    public readonly Address $to,
    public readonly string $first_name,
    public readonly string $last_name,
    public readonly string $full_name,
    public readonly string|null $postcode,
    public readonly string|null $phone,
    public readonly string|null $mobile,
    public readonly Schema $schema,
  )
  {
  }
}