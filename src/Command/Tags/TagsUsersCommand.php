<?php declare(strict_types=1);

namespace App\Command\Tags;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Type;

class TagsUsersCommand implements CommandInterface
{
    public $tags;
}
