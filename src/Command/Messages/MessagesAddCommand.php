<?php declare(strict_types=1);

namespace App\Command\Messages;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class MessagesAddCommand
{
    public $user_id;
    public $offer_want;
    public $subject;
    public $content;
    public $category_id;
    public $validity_days;
    public $amount;
    public $units;
    public $image_files;
    public $access;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('subject', new NotBlank());
        $metadata->addPropertyConstraint('subject', new Length(['max' => 200]));
        $metadata->addPropertyConstraint('location', new Length(['max' => 128]));
        $metadata->addPropertyConstraint('content', new NotBlank());
        $metadata->addPropertyConstraint('content', new Length(['min' => 10, 'max' => 5000]));
        $metadata->addPropertyConstraint('access', new NotBlank());
    }
}
