<?php declare(strict_types=1);

namespace App\Command\Tags;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class TagsOrderCommand implements CommandInterface
{
    #[Sequentially(constraints: [
        new NotBlank(groups: ['edit']),
        new Json(groups: ['edit']),
    ])]
    public $tags;
}
