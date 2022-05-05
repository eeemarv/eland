<?php declare(strict_types=1);

namespace App\Command\SupportForm;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class SupportFormCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotBlank(groups: ['send']),
        new Length(min: 10, max: 10000, groups: ['send']),
    ])]
    public $message;

    #[Type(type: 'bool', groups: ['send'])]
    public $cc;
}
