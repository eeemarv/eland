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
        new NotBlank(groups: ['send']),
        new Email(groups: ['send']),
        new EmailNotRegisteredYet(groups: ['send']),
    ])]
    public $email;

    #[NotBlank(groups: ['send'])]
    public $first_name;

    #[NotBlank(groups: ['send'])]
    public $last_name;

    #[Sequentially([
        new NotBlank(groups: ['send']),
        new Length(min: 4, max: 10, groups: ['send'])
    ])]
    public $postcode;

    public $mobile;

    public $phone;
}
