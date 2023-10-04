<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Type;

class UsersPostcodeCommand Implements CommandInterface
{
    #[Type(type: 'string')]
    #[Length(max: 10)]
    public $postcode;
}
