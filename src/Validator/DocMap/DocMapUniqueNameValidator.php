<?php declare(strict_types=1);

namespace App\Validator\DocMap;

use App\Command\Docs\DocsMapCommand;
use App\Repository\DocRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class DocMapUniqueNameValidator extends ConstraintValidator
{
    public function __construct(
        protected DocRepository $doc_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function validate($command, Constraint $constraint)
    {
        if (!$constraint instanceof DocMapUniqueName)
        {
            throw new UnexpectedTypeException($constraint, DocMapUniqueName::class);
        }

        if (!$command instanceof DocsMapCommand)
        {
            throw new UnexpectedTypeException($command, DocsMapCommand::class);
        }

        $name = $command->name;
        $id = $command->id;

        $is_unique = $this->doc_repository->is_unique_map_name_except_id($name, $id, $this->pp->schema());

        if (!$is_unique)
        {
            $this->context->buildViolation('doc_map.name_not_unique')
                ->atPath('name')
                ->addViolation();
            return;
        }
    }
}