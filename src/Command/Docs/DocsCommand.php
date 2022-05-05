<?php declare(strict_types=1);

namespace App\Command\Docs;

use App\Command\CommandInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;

class DocsCommand implements CommandInterface
{
    public $file_location;

    public $original_filename;

    #[Sequentially(constraints: [
        new NotBlank(groups: ['add']),
        new File(maxSize: '10M', groups: ['add']),
    ])]
    public $file;

    #[Length(max: 60, groups: ['add', 'edit'])]
    public $name;

    #[Length(max: 60, groups: ['add', 'edit'])]
    public $map_name;

    #[Sequentially(constraints: [
        new NotNull(groups: ['add', 'edit']),
        new Choice(choices: ['admin', 'user', 'guest'], groups: ['add', 'edit']),
    ])]
    public $access;
}
