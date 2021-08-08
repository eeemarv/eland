<?php declare(strict_types=1);

namespace App\Command\RegisterForm;

use App\Command\CommandInterface;
use App\Validator\EmailNotRegisteredYet;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class RegisterFormCommand implements CommandInterface
{
    public $email;
    public $first_name;
    public $last_name;
    public $postcode;
    public $mobile;
    public $phone;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('email', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Email(),
                new EmailNotRegisteredYet(),
            ],
        ]));
        $metadata->addPropertyConstraint('first_name', new NotBlank());
        $metadata->addPropertyConstraint('last_name', new NotBlank());
        $metadata->addPropertyConstraint('postcode', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Length(['min' => 4, 'max' => 10]),
            ],
        ]));
    }
}
