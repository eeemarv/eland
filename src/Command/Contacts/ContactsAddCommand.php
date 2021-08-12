<?php declare(strict_types=1);

namespace App\Command\Contacts;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class ContactsAddCommand implements CommandInterface
{
    #[NotBlank()]
    public $account_code;

    #[NotBlank()]
    #[Type('int')]
    public $contact_type_id;

    #[NotBlank()]
    #[Type('string')]
    public $value;

    #[Type('string')]
    public $comments;

    #[Type('string')]
    #[Choice(['admin', 'user', 'guest'])]
    public $access;
}
