<?php declare(strict_types=1);

namespace App\Command\Login;

use App\Command\CommandInterface;
use App\Validator\Login\Login;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Sequentially;

#[GroupSequence(['LoginCommand', 'login'])]
#[Login(groups: ['login'])]
class LoginCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new Length(['min' => 2, 'max' => 100]),
    ])]
    public $login;

    #[Sequentially([
        new NotBlank(),
        new Length(['min' => 5, 'max' => 100]),
    ])]
    public $password;

    public $is_master;
    public $id;
    public $password_hashing_updated;
}
