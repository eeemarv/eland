<?php declare(strict_types=1);

namespace App\Command\Index;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class IndexContactFormCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(groups: ['send']),
        new Email(groups: ['send']),
    ])]
    public $email;

    #[Sequentially([
        new NotBlank(groups: ['send']),
        new Length(min: 10, max: 5000, groups: ['send']),
    ])]
    public $message;
}
