<?php declare(strict_types=1);

namespace App\Command\ContactForm;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class ContactFormCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(groups: ['send']),
        new Email(groups: ['send']),
    ])]
    public $email;

    #[Sequentially([
        new NotBlank(groups: ['send']),
        new Length(max: 10000, groups: ['send']),
    ])]
    public $message;
}
