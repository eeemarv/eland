<?php declare(strict_types=1);

namespace App\Command\Tags;

use App\Command\CommandInterface;
use App\Validator\Tag\TagsUsersActive;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Unique;

class TagsUsersCommand implements CommandInterface
{
    #[Sequentially(constraints:[
        new Type(type: 'array'),
        new Unique(),
        new TagsUsersActive(),

    ])]
    public $tags;
}
