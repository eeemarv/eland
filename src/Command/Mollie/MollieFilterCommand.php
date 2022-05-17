<?php declare(strict_types=1);

namespace App\Command\Mollie;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

class MollieFilterCommand implements CommandInterface
{
    #[Type(type: 'string')]
    public $q;

    #[Type(type: 'int')]
    public $user;

    #[Choice(choices: ['open', 'paid', 'canceled'], multiple: true)]
    public $status;

    #[Type(type: 'string')]
    public $from_date;

    #[Type(type: 'string')]
    public $to_date;
}
