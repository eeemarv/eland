<?php declare(strict_types=1);

namespace App\Command\Messages;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class MessagesImagesCommand implements CommandInterface
{
    #[Sequentially([
        new NotBlank(),
        new Json(),
    ])]
    public $image_files;
}
