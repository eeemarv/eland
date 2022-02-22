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
        new NotBlank(),
        new Email(),
    ])]
    public $email;

    #[Sequentially([
        new NotBlank(),
        new Length(min: 10, max: 5000),
    ])]
    public $message;
}
