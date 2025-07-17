<?php declare(strict_types=1);

namespace App\Email;

use App\DTO\AddressAry;
use App\DTO\Schema;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Uid\Uuid;

#[AsMessage(transport: 'mail_dispatch')]
final class EmailDispatchMessage
{
    public function __construct(
        public readonly string $template,
        public readonly AddressAry $to,
        public readonly array $context = [],
        public readonly Address|null $from = null,
        public readonly Address|null $reply_to = null,
        public readonly AddressAry|null $cc = null,
        public readonly AddressAry|null $bcc = null,
        public readonly string|null $embedded_template = null,
        public readonly Uuid|null $bulk_id = null,
        public readonly bool $add_confirm_token = false,
        public readonly array|null $confirm_data = null,
        public readonly Schema|null $schema = null,
    )
    {
    }
}