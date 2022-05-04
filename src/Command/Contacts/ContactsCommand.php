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

#[UniqueEmailContact(groups: ['add', 'edit'])]
#[UrlContact(groups: ['add', 'eddit'])]
class ContactsCommand implements CommandInterface
{
    public $id;

    #[NotBlank(groups: ['add', 'edit'])]
    public $user_id;

    #[Sequentially(constraints: [
        new NotBlank(groups: ['add', 'edit']),
        new Type('int', groups: ['add', 'edit']),
    ])]
    public $contact_type_id;

    #[Sequentially(constraints:[
        new NotBlank(groups: ['add', 'edit']),
        new Type('string', groups: ['add', 'edit']),
        new Length(max: 120, groups: ['add', 'edit']),
    ])]
    public $value;

    #[Sequentially(constraints:[
        new Type('string', groups: ['add', 'edit']),
        new Length(max: 60, groups: ['add', 'edit']),
    ])]
    public $comments;

    #[Sequentially(constraints:[
        new NotBlank(groups: ['add', 'edit']),
        new Type('string', groups: ['add', 'edit']),
        new Choice(['admin', 'user', 'guest'], groups: ['add', 'edit']),
    ])]
    public $access;
}
