<?php declare(strict_types=1);

namespace App\Command\Forum;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class ForumPostCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotBlank(groups: ['add', 'edit']),
        new Length(max: 10000, groups: ['add', 'edit']),
    ])]
    public $content;
}
