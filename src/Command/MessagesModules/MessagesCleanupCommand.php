<?php declare(strict_types=1);

namespace App\Command\MessagesModules;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class MessagesCleanupCommand
{
    public $cleanup_enabled;
    public $cleanup_after_days;
    public $expires_at_days_default;
    public $expires_at_required;
    public $expires_at_switch_enabled;
    public $expire_notify;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('cleanup_after_days', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Positive(),
                new Range(['min' => 1, 'max' => 365]),
            ],
        ]));
        $metadata->addPropertyConstraint('expires_at_days_default', new Sequentially([
            'constraints'   => [
                new Positive(),
                new Range(['min' => 1, 'max' => 1460]),
            ],
        ]));
    }
}
