<?php declare(strict_types=1);

namespace App\Validator\Access;

use App\Service\ConfigService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class AccessValidator extends ConstraintValidator
{
  public function __construct(
    private readonly ConfigService $config_service,
    private readonly PageParamsService $pp,
  )
  {
  }

  public function validate($access, Constraint $constraint):void
  {
    if (!$constraint instanceof Access)
    {
      throw new UnexpectedTypeException($constraint, Access::class);
    }

    if (!isset($access))
    {
      return;
    }

    if (!is_string($access))
    {
      throw new UnexpectedTypeException($access, 'string');
    }

    $allowed_ary = ['admin', 'user'];

    if ($this->config_service->get_intersystem_en(
      schema: $this->pp->schema_o(),
    ))
    {
      $allowed_ary[] = 'guest';
    }

    if (!in_array($access, $allowed_ary))
    {
      $this->context->buildViolation('access.not_allowed')
        ->addViolation();
      return;
    }
  }
}