<?php declare(strict_types=1);

namespace App\Command\SupportForm;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class SupportFormCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new Length(min: 10, max: 10000),
    ])]
    public $message;

    #[Type('bool')]
    public $cc;
}
