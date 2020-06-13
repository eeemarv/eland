<?php declare(strict_types=1);

namespace App\Command\Forum;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class ForumTopicCommand
{
    public $subject;
    public $content;
    public $access;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('subject', new NotBlank());
        $metadata->addPropertyConstraint('content', new Sequentially([
            new NotBlank(),
            new Length(['min' => 10, 'max' => 5000]),
        ]));
        $metadata->addPropertyConstraint('access', new NotBlank());
    }
}
