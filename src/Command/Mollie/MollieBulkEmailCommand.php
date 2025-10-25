<?php declare(strict_types=1);

namespace App\Command\Mollie;

use App\Command\CommandInterface;
use App\Validator\BulkSelect\BulkSelectNotEmpty;
use App\Validator\PlainText\PlainTextLength;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class MollieBulkEmailCommand implements CommandInterface
{
  #[BulkSelectNotEmpty(message: 'bulk_select.not_empty.mollie_payment_requests')]
  public $selected;

  #[Sequentially(constraints: [
    new NotBlank(),
    new Type(type: 'string'),
    new Length(max: 200),
  ])]
  public $subject;

  #[Sequentially(constraints: [
    new Type(type: 'string'),
    new PlainTextLength(min: 30, max: 10000),
  ])]
  public $content;

  #[Type(type: 'bool')]
  public $copy;

  #[Type(type: 'bool')]
  #[IsTrue()]
  public $verify;
}
