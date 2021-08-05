<?php declare(strict_types=1);

namespace App\Command\Forum;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ForumTopicCommand implements CommandInterface
{
    #[NotBlank()]
    #[Length(max: 200)]
    public $subject;

    #[NotBlank()]
    #[Length(max: 10000)]
    public $content;

    #[NotBlank()]
    #[Choice(['admin', 'user', 'guest'])]
    public $access;
}
