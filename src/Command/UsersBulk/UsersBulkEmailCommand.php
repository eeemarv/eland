<?php declare(strict_types=1);

namespace App\Command\UsersBulk;

use App\Command\CommandInterface;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use App\Validator\PlainText\PlainTextLength;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class UsersBulkEmailCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.users')]
  public mixed $selected;

  #[Sequentially(constraints: [
    new NotBlank(),
    new Type(type: 'string'),
    new Length(max: 200),
  ])]
  public mixed $subject;

  #[Sequentially(constraints: [
    new Type(type: 'string'),
    new PlainTextLength(min: 30, max: 10000),
  ])]
  public mixed $json_content;

  #[Type(type: 'bool')]
  public mixed $copy;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public mixed $verify;
}
