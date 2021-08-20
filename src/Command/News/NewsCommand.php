<?php declare(strict_types=1);

namespace App\Command\News;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class NewsCommand implements CommandInterface
{
    public $id;

    #[NotBlank()]
    #[Length(max: 200)]
    public $subject;

    public $event_at;

    #[Length(max: 128)]
    public $location;

    #[NotBlank()]
    #[Length(min: 10, max: 100000)]
    public $content;

    #[Type('string')]
    #[NotNull()]
    #[Choice(['admin', 'user', 'guest'])]
    public $access;
}
