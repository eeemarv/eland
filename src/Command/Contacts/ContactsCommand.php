<?php declare(strict_types=1);

namespace App\Command\Contacts;

use App\Command\CommandInterface;
use App\Validator\Contact\UniqueEmailContact;
use App\Validator\Contact\UrlContact;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

#[UniqueEmailContact()]
#[UrlContact()]
class ContactsCommand implements CommandInterface
{
    public $id;

    #[NotBlank()]
    public $user_id;

    #[NotBlank()]
    #[Type('int')]
    public $contact_type_id;

    #[NotBlank()]
    #[Type('string')]
    #[Length(max: 120)]
    public $value;

    #[Type('string')]
    #[Length(max: 60)]
    public $comments;

    #[Type('string')]
    #[NotNull()]
    #[Choice(['admin', 'user', 'guest'])]
    public $access;
}
