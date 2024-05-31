<?php declare(strict_types=1);

namespace App\Command\Index;

use App\Command\CommandInterface;
use App\Validator\Captcha\Captcha;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class IndexContactFormCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotBlank(groups: ['send']),
        new Email(groups: ['send']),
    ])]
    public $email_address;

    #[Sequentially(constraints: [
        new NotBlank(groups: ['send']),
        new Length(min: 10, max: 5000, groups: ['send']),
    ])]
    public $message;

    #[Captcha(groups: ['send'])]
    public $captcha;
}
