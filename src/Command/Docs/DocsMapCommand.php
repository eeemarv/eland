<?php declare(strict_types=1);

namespace App\Command\Docs;

use App\Command\CommandInterface;
use App\Validator\DocMap\DocMapUniqueName;
use Symfony\Component\Validator\Constraints\NotBlank;

#[DocMapUniqueName(groups: ['edit'])]
class DocsMapCommand implements CommandInterface
{
    #[NotBlank(groups: ['edit'])]
    public $name;

    public $id;
}
