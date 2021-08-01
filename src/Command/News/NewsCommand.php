<?php declare(strict_types=1);

namespace App\Command\News;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class NewsCommand implements CommandInterface
{
    public $subject;
    public $event_at;
    public $location;
    public $content;
    public $access;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('subject', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Length(['max' => 200]),
            ],
        ]));
        $metadata->addPropertyConstraint('location', new Length(['max' => 128]));
        $metadata->addPropertyConstraint('content', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Length(['min' => 10, 'max' => 100000]),
            ],
        ]));
        $metadata->addPropertyConstraint('access', new NotBlank());
    }
}
