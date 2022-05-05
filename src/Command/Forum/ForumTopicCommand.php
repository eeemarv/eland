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
    #[Sequentially(constraints: [
        new NotBlank(groups: ['add', 'edit']),
        new Length(max: 200, groups: ['add', 'edit']),
    ])]
    public $subject;

    #[Sequentially(constraints: [
        new NotBlank(groups: ['add', 'edit']),
        new Length(max: 10000, groups: ['add', 'edit']),
    ])]
    public $content;

    #[Sequentially(constraints: [
        new NotNull(groups: ['add', 'edit', 'del']),
        new Choice(choices: ['admin', 'user', 'guest'], groups: ['add', 'edit']),
    ])]
    public $access;
}
