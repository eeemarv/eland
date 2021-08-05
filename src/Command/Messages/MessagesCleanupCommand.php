<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Attributes\ConfigMap;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

class MessagesCleanupCommand implements CommandInterface
{
    #[Positive()]
    #[Length(min: 1, max: 1460)]
    #[ConfigMap(type: 'int', key: 'messages.fields.expires_at.days_default')]
    public $expires_at_days_default;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'messages.fields.expires_at.required')]
    public $expires_at_required;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'messages.fields.expires_at.switch_enabled')]
    public $expires_at_switch_enabled;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'messages.cleanup.enabled')]
    public $cleanup_enabled;

    #[NotBlank()]
    #[Positive()]
    #[Length(min: 1, max: 365)]
    #[ConfigMap(type: 'int', key: 'messages.cleanup.after_days')]
    public $cleanup_after_days;

    #[Type('bool')]
    #[ConfigMap(type: 'bool', key: 'messages.expire.notify')]
    public $expire_notify;
}
