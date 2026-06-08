<?php declare(strict_types=1);

namespace App\Command\Cms;

use Symfony\Component\Validator\Constraints\ExpressionSyntax;
use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class CmsEditCommand
{
  #[Sequentially(constraints: [
    new NotNull(),
    new Type(type: 'string'),
  ])]
  public mixed $route;

  #[ExpressionSyntax(
    allowedVariables: ['0', '1']
  )]
  public mixed $route_en;

  #[ExpressionSyntax(
    allowedVariables: ['admin', 'user', 'guest', 'anonymous']
  )]
  public mixed $role;

  #[ExpressionSyntax(
    allowedVariables: ['0', '1']
  )]
  public mixed $role_en;

  #[Sequentially(constraints: [
    new NotNull(),
    new Json(),
  ])]
  public mixed $all_params;

  #[Sequentially(constraints: [
    new NotNull(),
    new Json(),
  ])]
  public mixed $content;
}
