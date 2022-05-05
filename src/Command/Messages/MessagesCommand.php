<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use App\Validator\Category\CategoryIsLeaf;
use App\Validator\User\ActiveUser;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Sequentially;

// not used yet
class MessagesCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotBlank(),
        new ActiveUser(),
    ], groups: ['user_id'])]
    public $user_id;

    #[Sequentially(constraints: [
        new NotNull(),
        new Choice(choices: ['offer', 'want']),
    ], groups: ['common'])]
    public $offer_want;

    #[Sequentially(constraints: [
        new NotNull(),
        new Choice(choices: ['service', 'stuff']),
    ], groups: ['service_stuff'])]
    public $service_stuff;

    #[Sequentially([
        new NotBlank(),
        new Length(max: 200),
    ], groups: ['common'])]
    public $subject;

    #[Sequentially([
        new NotBlank(),
        new Length(min: 10, max: 5000),
    ], groups: ['common'])]
    public $content;

    #[Sequentially([
        new NotBlank(),
        new CategoryIsLeaf(),
    ], groups: ['category_id'])]
    public $category_id;

    #[Sequentially([
        new NotBlank(),
    ], groups: ['expires_at_required'])]
    public $expires_at;

    #[Sequentially([
        new NotBlank(),
        new Choice(['temporal', 'permanent']),
    ], groups: ['expires_at_switch'])]
    public $expires_at_switch;

    #[Sequentially([
        new Positive(),
    ], groups: ['units'])]
    public $amount;

    #[Sequentially([
        new Length(max: 15),
    ], groups: ['units'])]
    public $units;

    #[Sequentially([
        new NotBlank(),
        new Json(),
    ], groups: ['common'])]
    public $image_files;

    #[Sequentially([
        new NotBlank(),
        new Choice(['admin', 'user', 'guest']),
    ], groups: ['common'])]
    public $access;
}
