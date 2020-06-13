<?php declare(strict_types=1);

namespace App\Command\Index;

use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class IndexContactFormCommand
{
    public $email;
    public $message;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('email', new Sequentially([
            new NotBlank(),
            new Email(),
        ]));
        $metadata->addPropertyConstraint('message', new Sequentially([
            new NotBlank(),
            new Length(['min' => 10, 'max' => 5000]),
        ]));
    }
}
