<?php declare(strict_types=1);

namespace App\Command\ContactTypes;

use App\Command\CommandInterface;
use App\Validator\ContactType\UniqueContactType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

#[UniqueContactType(['properties' => ['name', 'abbrev']], groups: ['add', 'edit'])]
class ContactTypesCommand implements CommandInterface
{
    public $id;

    #[Sequentially(constraints: [
        new NotBlank(groups: ['add', 'edit']),
        new Length(max: 20, groups: ['add', 'edit']),
    ])]
    public $name;

    #[Sequentially(constraints: [
        new NotBlank(groups: ['add', 'edit']),
        new Length(max: 5, groups: ['add', 'edit', 'del']),
    ])]
    public $abbrev;
}
