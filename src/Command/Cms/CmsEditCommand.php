<?php declare(strict_types=1);

namespace App\Command\Cms;

use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class CmsEditCommand
{
    #[NotNull()]
    #[Type('string')]
    public $route;

    #[NotNull()]
    #[Json()]
    public $all_params;

    #[NotNull()]
    #[Json()]
    public $content;
}
