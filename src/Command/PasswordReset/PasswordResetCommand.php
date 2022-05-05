<?php declare(strict_types=1);

namespace App\Command\PasswordReset;

use App\Command\CommandInterface;
use App\Validator\Email\EmailUniqueToActiveUser;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class PasswordResetCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotBlank(groups: ['send']),
        new Email(groups: ['send']),
        new EmailUniqueToActiveUser(groups: ['send']),
    ])]
    public $email;
}
