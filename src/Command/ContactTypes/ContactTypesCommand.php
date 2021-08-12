<?php declare(strict_types=1);

namespace App\Command\ContactTypes;

use App\Command\CommandInterface;
use App\Validator\ContactType\UniqueContactType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[UniqueContactType(['properties' => ['name', 'abbrev']])]
class ContactTypesCommand implements CommandInterface
{
    public $id;

    #[NotBlank()]
    #[Length(max: 20)]
    public $name;

    #[NotBlank()]
    #[Length(max: 5)]
    public $abbrev;
}
