<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use App\Validator\Category\CategoryIsLeaf;
use App\Validator\User\ActiveUser;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class MessagesCommand implements CommandInterface
{
    public $user_id;
    public $offer_want;
    public $service_stuff;
    public $subject;
    public $content;
    public $category_id;
    public $expires_at;
    public $expires_at_switch;
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
            'groups' => ['user_id'],
        ]));
        $metadata->addPropertyConstraint('offer_want', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Choice(['offer', 'want']),
            ],
            'groups' => ['common'],
        ]));
        $metadata->addPropertyConstraint('service_stuff', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Choice(['service', 'stuff']),
            ],
            'groups' => ['service_stuff'],
        ]));
        $metadata->addPropertyConstraint('subject', new Sequentially([
            'constraints' => [
                new NotBlank(),
                new Length(['max' => 200]),
            ],
            'groups' => ['common'],
        ]));
        $metadata->addPropertyConstraint('content', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Length(['min' => 10, 'max' => 5000]),
            ],
            'groups' => ['common'],
        ]));
        $metadata->addPropertyConstraint('expires_at', new Sequentially([
            'constraints'   => [
                new NotBlank(),
            ],
            'groups' => ['expires_at_required'],
        ]));
        $metadata->addPropertyConstraint('expires_at_switch', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Choice(['temporal', 'permanent']),
            ],
            'groups' => ['expires_at_switch'],
        ]));
        $metadata->addPropertyConstraint('category_id', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new CategoryIsLeaf(),
            ],
            'groups' => ['category_id'],
        ]));
        $metadata->addPropertyConstraint('units', new Sequentially([
            'constraints'   => [
                new Length(['max' => 15]),
            ],
            'groups'    => ['units'],
        ]));
        $metadata->addPropertyConstraint('image_files', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Json(),
            ],
            'groups' => ['common'],
        ]));
        $metadata->addPropertyConstraint('access', new Sequentially([
            'constraints'   => [
                new NotBlank(),
                new Choice(['admin', 'user', 'guest']),
            ],
            'groups' => ['common'],
        ]));
    }
}
