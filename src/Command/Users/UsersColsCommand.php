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
  public mixed $code;

  #[Type(type: 'bool')]
  public mixed $name;

  #[Type(type: 'bool')]
  public mixed $full_name;

  #[Type(type: 'bool')]
  public mixed $full_name_access;

  #[Type(type: 'bool')]
  public mixed $postcode;

  #[Type(type: 'bool')]
  public mixed $role;

  #[Type(type: 'bool')]
  public mixed $balance;

  #[Type(type: 'bool')]
  public mixed $balance_on_date;

  #[Type(type: 'string')]
  public mixed $balance_date;

  #[Type(type: 'bool')]
  public mixed $min_limit;

  #[Type(type: 'bool')]
  public mixed $max_limit;

  #[Type(type: 'bool')]
  public mixed $comments;

  #[Type(type: 'bool')]
  public mixed $hobbies;

  #[Type(type: 'bool')]
  public mixed $birthdate;

  #[Type(type: 'bool')]
  public mixed $admin_comments;

  #[Type(type: 'bool')]
  public mixed $periodic_overview;

  #[Type(type: 'bool')]
  public mixed $created_at;

  #[Type(type: 'bool')]
  public mixed $last_edit_at;

  #[Type(type: 'bool')]
  public mixed $activated_at;

  #[Type(type: 'bool')]
  public mixed $last_login_at;

  #[Type(type: 'bool')]
  public mixed $tags;

  #[Type(type: 'array')]
  #[All([
    new Type(type: 'bool'),
  ])]
  public mixed $contacts;

  #[Type(type: 'bool')]
  public mixed $distance;

  #[Type(type: 'bool')]
  public mixed $mollie;

  #[Type(type: 'bool')]
  public mixed $wants;

  #[Type(type: 'bool')]
  public mixed $offers;

  #[Type(type: 'bool')]
  public mixed $offers_and_wants;

  #[Type(type: 'int')]
  public mixed $transactions_days;

  #[Type(type: 'int')]
  public mixed $transactions_exclude_code;

  #[Type(type: 'bool')]
  public mixed $transactions_in;

  #[Type(type: 'bool')]
  public mixed $transactions_out;

  #[Type(type: 'bool')]
  public mixed $transactions_total;

  #[Type(type: 'bool')]
  public mixed $amount_in;

  #[Type(type: 'bool')]
  public mixed $amount_out;

  #[Type(type: 'bool')]
  public mixed $amount_total;
}
