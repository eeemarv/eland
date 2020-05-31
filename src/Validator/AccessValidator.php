<?php declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;
use App\Service\ConfigService;

class AccessValidator extends ConstraintValidator
{
    protected ConfigService $config_service;
    protected PageParamsService $pp;

    public function __construct(
        ConfigService $config_service,
        PageParamsService $pp
    )
    {
        $this->config_service = $config_service;
        $this->pp = $pp;
    }

    public function validate($access, Constraint $constraint)
    {
        if (!$constraint instanceof Access)
        {
            throw new UnexpectedTypeException($constraint, Access::class);
        }

        if (!is_string($access))
        {
            throw new UnexpectedTypeException($access, 'string');
        }

        if (!in_array($access, ['anonymous', 'guest', 'user', 'admin']))
        {
            throw new UnexpectedTypeException($access, 'access');
        }

        $intersystem_en = $this->config_service->get_intersystem_en($this->pp->schema());




    }
}