<?php declare(strict_types=1);

namespace App\Command\Forum;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ForumPostCommand implements CommandInterface
{
    #[NotBlank()]
    #[Length(max: 10000)]
    public $content;
}
