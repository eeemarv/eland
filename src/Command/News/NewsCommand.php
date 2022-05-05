<?php declare(strict_types=1);

namespace App\Command\News;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

#[GroupSequence(['NewsCommand', 'add', 'edit', 'del'])]
class NewsCommand implements CommandInterface
{
    public $id;

    #[Sequentially([
        new NotBlank(groups: ['add', 'edit']),
        new Length(max: 200, groups: ['add', 'edit']),
    ])]
    public $subject;

    public $event_at;

    #[Length(max: 128, groups: ['add', 'edit'])]
    public $location;

    #[Sequentially([
        new NotBlank(groups: ['add', 'edit']),
        new Length(min: 10, max: 10000, groups: ['add', 'edit']),
    ])]
    public $content;

    #[Sequentially([
        new NotNull(groups: ['add', 'edit']),
        new Type('string', groups: ['add', 'edit']),
        new Choice(['admin', 'user', 'guest'], groups: ['add', 'edit', 'del']),
    ])]
    public $access;
}
