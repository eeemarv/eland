<?php declare(strict_types=1);

namespace App\Command\Support;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class SupportCommand
{
    public $message;
    public $cc;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('message', new NotBlank());
        $metadata->addPropertyConstraint('message', new Length(['min' => 10, 'max' => 5000]));
    }
}
