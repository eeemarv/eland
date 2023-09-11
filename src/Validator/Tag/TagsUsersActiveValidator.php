<?php declare(strict_types=1);

namespace App\Validator\Tag;

use App\Command\Tags\TagsDefCommand;
use App\Repository\TagRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Service\PageParamsService;
use App\Validator\Tag\TagUniqueTxt;

class TagsUsersActiveValidator extends ConstraintValidator
{
    public function __construct(
        protected TagRepository $tag_repository,
        protected PageParamsService $pp
    )
    {
    }

    public function validate($tags, Constraint $constraint):void
    {
        if (!$constraint instanceof TagsUsersActive)
        {
            throw new UnexpectedTypeException($constraint, TagsUsersActive::class);
        }

        if (!is_array($tags))
        {
            throw new UnexpectedTypeException($tags, 'array');
        }

        $active_tags = $this->tag_repository->get_all('users', $this->pp->schema(), active_only: true);

        $active_id_keys = [];

        foreach ($active_tags as $tag)
        {
            $active_id_keys[$tag['id']] = true;
        }

        foreach ($tags as $tag_id)
        {
            if (!isset($active_id_keys[$tag_id]))
            {
                $this->context->buildViolation('tags_users.not_active')
                    ->addViolation();
                return;
            }
        }
    }
}