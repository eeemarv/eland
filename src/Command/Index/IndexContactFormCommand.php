<?php declare(strict_types=1);

namespace App\Command\Index;

use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class IndexContactFormCommand
{
    public $email;
    public $message;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('email', new NotBlank());
        $metadata->addPropertyConstraint('email', new Email());
        $metadata->addPropertyConstraint('message', new NotBlank());
        $metadata->addPropertyConstraint('message', new Length(['min' => 10, 'max' => 5000]));
    }
}
