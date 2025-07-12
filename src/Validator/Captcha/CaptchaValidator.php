<?php declare(strict_types=1);

namespace App\Validator\Captcha;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Redis;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

class CaptchaValidator extends ConstraintValidator
{
  const KEY_PREFIX = 'captcha.';

  public function __construct(
    private readonly Redis $redis,
    private readonly RequestStack $request_stack,
    #[Autowire(param: 'kernel.debug')]
    private readonly bool $debug_mode
  )
  {
  }

  public function validate($captcha, Constraint $constraint):void
  {
    if (!$constraint instanceof Captcha)
    {
      throw new UnexpectedTypeException($constraint, Captcha::class);
    }

    if ($this->debug_mode)
    {
      return;
    }

    if (empty($captcha))
    {
      $this->context->buildViolation('not_empty')
        ->addViolation();
      return;
    }

    if (!is_string($captcha))
    {
      throw new UnexpectedTypeException($captcha, 'string');
    }

    $request = $this->request_stack->getCurrentRequest();

    if (!$request->request->has('captcha_token'))
    {
      $this->context->buildViolation('captcha.no_token')
        ->addViolation();
      return;
    }

    $captcha_token = $request->request->get('captcha_token');
    $key = self::KEY_PREFIX . $captcha_token;

    if (!$this->redis->exists($key))
    {
      $this->context->buildViolation('captcha.token_not_found')
        ->addViolation();
      return;
    }

    $captcha_code = $this->redis->get($key);
    $this->redis->del($key);

    if ($captcha !== $captcha_code)
    {
      $this->context->buildViolation('captcha.not_correct')
        ->addViolation();
      return;
    }
  }
}