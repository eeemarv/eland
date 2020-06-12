<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Validator\Category\Category;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class MessagesAddCommand
{
    public $user_id;
    public $offer_want;
    public $subject;
    public $content;
    public $category_id;
    public $expires_at;
    public $amount;
    public $units;
    public $image_files;
    public $access;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('offer_want', new Sequentially([
            new NotBlank(),
            new Choice(['offer', 'want']),
        ]));
        $metadata->addPropertyConstraint('subject', new Sequentially([
            new NotBlank(),
            new Length(['max' => 200]),
        ]));
        $metadata->addPropertyConstraint('content', new Sequentially([
            new NotBlank(),
            new Length(['min' => 10, 'max' => 5000]),
        ]));
        $metadata->addPropertyConstraint('category_id', new Sequentially([
            new NotBlank(),
            new Category(),
        ]));
        $metadata->addPropertyConstraint('units', new Length(['max' => 15]));
        $metadata->addPropertyConstraint('access', new NotBlank());
    }
}
