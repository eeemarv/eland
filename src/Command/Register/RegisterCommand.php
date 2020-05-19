<?php declare(strict_types=1);

namespace App\Command\Register;

use App\Validator\EmailNotRegisteredYet;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class RegisterCommand
{
    public $email;
    public $first_name;
    public $last_name;
    public $postcode;
    public $mobile;
    public $phone;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('email', new NotBlank());
        $metadata->addPropertyConstraint('email', new Email());
        $metadata->addPropertyConstraint('email', new EmailNotRegisteredYet(['groups' => ['NotRegisteredYet']]));
        $metadata->addPropertyConstraint('first_name', new NotBlank());
        $metadata->addPropertyConstraint('last_name', new NotBlank());
        $metadata->addPropertyConstraint('postcode', new NotBlank());
        $metadata->addPropertyConstraint('postcode', new Length(['min' => 4, 'max' => 10]));
        $metadata->setGroupSequence(['RegisterCommand', 'NotRegisteredYet']);
    }
}
