<?php declare(strict_types=1);

namespace App\Command\RegisterForm;

use App\Command\CommandInterface;
use App\Validator\Email\EmailNotRegisteredYet;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class RegisterFormCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new Email(),
        new EmailNotRegisteredYet(),
    ])]
    public $email;

    #[NotBlank()]
    public $first_name;

    #[NotBlank()]
    public $last_name;

    #[Sequentially([
        new NotBlank(),
        new Length(min: 4, max: 10)
    ])]
    public $postcode;

    public $mobile;

    public $phone;
}
