<?php declare(strict_types=1);

namespace App\Command\Users;

use App\Command\ArrayCleanConvertTrait;
use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Type;

class UsersColsCommand implements CommandInterface
{
  use ArrayCleanConvertTrait;

  #[Type(type: 'bool')]
  public $code;

  #[Type(type: 'bool')]
  public $name;

  #[Type(type: 'bool')]
  public $full_name;

  #[Type(type: 'bool')]
  public $postcode;

  #[Type(type: 'bool')]
  public $role;

  #[Type(type: 'bool')]
  public $balance;

  #[Type(type: 'bool')]
  public $balance_on_date;

  #[Type(type: 'string')]
  public $balance_date;

  #[Type(type: 'bool')]
  public $min_limit;

  #[Type(type: 'bool')]
  public $max_limit;

  #[Type(type: 'bool')]
  public $comments;

  #[Type(type: 'bool')]
  public $hobbies;

  #[Type(type: 'bool')]
  public $birthdate;

  #[Type(type: 'bool')]
  public $admin_comments;

  #[Type(type: 'bool')]
  public $periodic_overview;

  #[Type(type: 'bool')]
  public $created_at;

  #[Type(type: 'bool')]
  public $last_edit_at;

  #[Type(type: 'bool')]
  public $activated_at;

  #[Type(type: 'bool')]
  public $last_login_at;

  #[Type(type: 'array')]
  #[All([
    new Type(type: 'bool'),
  ])]
  public $contacts;

  #[Type(type: 'bool')]
  public $distance;

  #[Type(type: 'bool')]
  public $mollie;

  #[Type(type: 'bool')]
  public $wants;

  #[Type(type: 'bool')]
  public $offers;

  #[Type(type: 'bool')]
  public $offers_and_wants;

  #[Type(type: 'int')]
  public $transactions_days;

  #[Type(type: 'int')]
  public $transactions_exclude_code;

  #[Type(type: 'bool')]
  public $transactions_in;

  #[Type(type: 'bool')]
  public $transactions_out;

  #[Type(type: 'bool')]
  public $transactions_total;

  #[Type(type: 'bool')]
  public $amount_in;

  #[Type(type: 'bool')]
  public $amount_out;

  #[Type(type: 'bool')]
  public $amount_total;
}
