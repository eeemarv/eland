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
    private readonly DocRepository $doc_repository,
    private readonly PageParamsService $pp,
  )
  {
  }

  public function validate($command, Constraint $constraint):void
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

    $is_unique = $this->doc_repository->is_unique_map_name_except_id(
      name: $name,
      map_id: $id,
      schema: $this->pp->schema_o(),
    );

    if (!$is_unique)
    {
      $this->context->buildViolation('doc_map.name_not_unique')
        ->atPath('name')
        ->addViolation();
      return;
    }
  }
}