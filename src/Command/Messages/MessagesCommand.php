<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Validator\Category\CategoryExists;
use App\Validator\Category\CategoryIsLeaf;
use App\Validator\User\ActiveUser;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class MessagesCommand
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
        $metadata->addPropertyConstraint('user_id', new Sequentially([
            'constraints' => [
                new NotBlank(),
                new ActiveUser(),
            ],
            'groups' => ['admin'],
        ]));
        $metadata->addPropertyConstraint('offer_want', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Choice(['offer', 'want']),
            ],
            'groups' => ['user'],
        ]));
        $metadata->addPropertyConstraint('subject', new Sequentially([
            'constraints' => [
                new NotBlank(),
                new Length(['max' => 200]),
            ],
            'groups' => ['user'],
        ]));
        $metadata->addPropertyConstraint('content', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Length(['min' => 10, 'max' => 5000]),
            ],
            'groups' => ['user'],
        ]));
        $metadata->addPropertyConstraint('category_id', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new CategoryExists(),
                new CategoryIsLeaf(),
            ],
            'groups' => ['user'],
        ]));
        $metadata->addPropertyConstraint('units', new Length(['max' => 15, 'groups' => ['user']]));
        $metadata->addPropertyConstraint('image_files', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Json(),
            ],
            'groups' => ['user'],
        ]));
        $metadata->addPropertyConstraint('access', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Choice(['admin', 'user', 'guest']),
            ],
            'groups' => ['user'],
        ]));
    }
}
