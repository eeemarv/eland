<?php declare(strict_types=1);

namespace App\Validator\Tag;

use App\Command\Tags\TagsDefCommand;
use App\Repository\TagRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;
use App\Validator\Tag\TagUniqueTxt;

class TagUniqueTxtValidator extends ConstraintValidator
{
    public function __construct(
        protected TagRepository $tag_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function validate($tags_def_command, Constraint $constraint)
    {
        if (!$constraint instanceof TagUniqueTxt)
        {
            throw new UnexpectedTypeException($constraint, TagUniqueTxt::class);
        }

        if (!$tags_def_command instanceof TagsDefCommand)
        {
            throw new UnexpectedTypeException($tags_def_command, TagsDefCommand::class);
        }

        $txt = $tags_def_command->txt;
        $id = $tags_def_command->id;
        $tag_type = $tags_def_command->tag_type;

        $is_unique = $this->tag_repository->is_unique_txt_except_id($txt, $id, $tag_type, $this->pp->schema());

        if (!$is_unique)
        {
            $this->context->buildViolation('tag.txt_not_unique')
                ->atPath('txt')
                ->addViolation();
            return;
        }
    }
}