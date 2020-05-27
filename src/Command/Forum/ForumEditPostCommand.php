<?php declare(strict_types=1);

namespace App\Command\Forum;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class ForumEditPostCommand
{
    public $content;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('content', new NotBlank());
        $metadata->addPropertyConstraint('content', new Length(['min' => 10, 'max' => 5000]));
    }
}
