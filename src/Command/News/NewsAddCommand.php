<?php declare(strict_types=1);

namespace App\Command\News;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class NewsAddCommand
{
    public $subject;
    public $event_at;
    public $location;
    public $content;
    public $access;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('subject', new NotBlank());
        $metadata->addPropertyConstraint('content', new NotBlank());
        $metadata->addPropertyConstraint('content', new Length(['min' => 10, 'max' => 100000]));
        $metadata->addPropertyConstraint('access', new NotBlank());
    }
}
