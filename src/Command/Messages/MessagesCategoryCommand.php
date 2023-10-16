<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use App\Validator\Category\CategoryIsLeaf;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class MessagesCategoryCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new CategoryIsLeaf(),
    ])]
    public $category_id;
}
