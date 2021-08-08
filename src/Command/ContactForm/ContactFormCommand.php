<?php declare(strict_types=1);

namespace App\Command\ContactForm;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContactFormCommand implements CommandInterface
{
    #[NotBlank()]
    #[Email()]
    public $email;

    #[NotBlank()]
    #[Length(max: 10000)]
    public $message;
}
