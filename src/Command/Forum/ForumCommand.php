<?php declare(strict_types=1);

namespace App\Command\Forum;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class ForumCommand
{
    public $subject;
    public $content;
    public $access;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('subject', new NotBlank([
            'groups'    => ['topic'],
        ]));
        $metadata->addPropertyConstraint('content', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Length(['min' => 10, 'max' => 5000]),
            ],
            'groups'    => ['post', 'topic'],
        ]));
        $metadata->addPropertyConstraint('access', new NotBlank([
            'groups'    => ['topic'],
        ]));
    }
}
