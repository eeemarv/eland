<?php declare(strict_types=1);

namespace App\Command\Forum;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;

class ForumTopicCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new Length(max: 200),
    ])]
    public $subject;

    #[Sequentially([
        new NotBlank(),
        new Length(max: 10000),
    ])]
    public $content;

    #[Sequentially([
        new NotNull(),
        new Choice(['admin', 'user', 'guest']),
    ])]
    public $access;
}
