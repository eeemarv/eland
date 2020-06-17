<?php declare(strict_types=1);

namespace App\Validator\DocMap;

use App\Command\Docs\DocsMapCommand;
use App\Repository\DocRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;

class UniqueDocMapValidator extends ConstraintValidator
{
    protected DocRepository $doc_repository;
    protected PageParamsService $pp;

    public function __construct(
        DocRepository $doc_repository,
        PageParamsService $pp
    )
    {
        $this->doc_repository = $doc_repository;
        $this->pp = $pp;
    }

    public function validate($docs_map_command, Constraint $constraint)
    {
        if (!$constraint instanceof UniqueDocMap)
        {
            throw new UnexpectedTypeException($constraint, UniqueDocMap::class);
        }

        if (!$docs_map_command instanceof DocsMapCommand)
        {
            throw new UnexpectedTypeException($docs_map_command, DocsMapCommand::class);
        }

        $name = $docs_map_command->name;
        $id = $docs_map_command->id;

        $is_unique = $this->doc_repository->is_unique_map_name_except_id($name, $id, $this->pp->schema());

        if (!$is_unique)
        {
            $this->context->buildViolation('doc_map.not_unique')
                ->atPath('name')
                ->addViolation();
            return;
        }
    }
}