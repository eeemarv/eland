<?php declare(strict_types=1);

namespace App\Command\Messages;

use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class MessagesEditCommand
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
        $metadata->addPropertyConstraint('offer_want', new Choice(['offer', 'want']));
        $metadata->addPropertyConstraint('subject', new NotBlank());
        $metadata->addPropertyConstraint('subject', new Length(['max' => 200]));
        $metadata->addPropertyConstraint('content', new NotBlank());
        $metadata->addPropertyConstraint('content', new Length(['min' => 10, 'max' => 5000]));
        $metadata->addPropertyConstraint('units', new Length(['max' => 15]));
        $metadata->addPropertyConstraint('access', new NotBlank());
    }
}
