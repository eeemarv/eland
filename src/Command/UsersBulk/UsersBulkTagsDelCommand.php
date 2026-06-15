<?php declare(strict_types=1);

namespace App\Command\UsersBulk;

use App\Command\CommandInterface;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use App\Validator\Tag\TagsUsersActive;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Unique;

class UsersBulkTagsDelCommand Implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.users')]
  public mixed $selected;

  #[Sequentially(constraints:[
    new Type(type: 'array'),
    new Unique(),
    new TagsUsersActive(),
    new Count(exactly: 1),
  ])]
  public mixed $tags;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
