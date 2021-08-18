<?php declare(strict_types=1);

namespace App\Command\Docs;

use App\Command\CommandInterface;
use App\Validator\DocMap\DocMapUniqueName;
use Symfony\Component\Validator\Constraints\NotBlank;

#[DocMapUniqueName()]
class DocsMapCommand implements CommandInterface
{
    #[NotBlank()]
    public $name;

    public $id;
}
