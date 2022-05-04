<?php declare(strict_types=1);

namespace App\Command\Contacts;

use App\Command\CommandInterface;
use App\Validator\Contact\UniqueEmailContact;
use App\Validator\Contact\UrlContact;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

#[UniqueEmailContact()]
#[UrlContact()]
class ContactsCommand implements CommandInterface
{
    public $id;

    #[NotBlank()]
    public $user_id;

    #[Sequentially(constraints: [
        new NotBlank(),
        new Type('int'),
    ])]
    public $contact_type_id;

    #[Sequentially(constraints:[
        new NotBlank(),
        new Type('string'),
        new Length(max: 120),
    ])]
    public $value;

    #[Sequentially(constraints:[
        new Type('string'),
        new Length(max: 60),
    ])]
    public $comments;

    #[Sequentially(constraints:[
        new NotBlank(),
        new Type('string'),
        new Choice(['admin', 'user', 'guest']),
    ])]
    public $access;
}
