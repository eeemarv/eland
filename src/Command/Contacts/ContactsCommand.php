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
#[UrlContact(groups: ['add', 'edit'])]
class ContactsCommand implements CommandInterface
{
  public mixed $id;

  #[NotBlank(groups: ['add', 'edit'])]
  public mixed $user_id;

  #[Sequentially(constraints: [
    new NotBlank(groups: ['add', 'edit']),
    new Type(type: 'int', groups: ['add', 'edit']),
  ])]
  public mixed $contact_type_id;

  #[Sequentially(constraints:[
    new NotBlank(groups: ['add', 'edit']),
    new Type(type: 'string', groups: ['add', 'edit']),
    new Length(max: 120, groups: ['add', 'edit']),
  ])]
  public mixed $value;

  #[Sequentially(constraints:[
    new Type(type: 'string', groups: ['add', 'edit']),
    new Length(max: 60, groups: ['add', 'edit']),
  ])]
  public mixed $comments;

  #[Sequentially(constraints:[
    new NotBlank(groups: ['add', 'edit']),
    new Type(type: 'string', groups: ['add', 'edit']),
    new Choice(choices: ['admin', 'user', 'guest'], groups: ['add', 'edit', 'del']),
  ])]
  public mixed $access;
}
