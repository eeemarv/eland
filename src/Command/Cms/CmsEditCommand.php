<?php declare(strict_types=1);

namespace App\Command\Cms;

use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class CmsEditCommand
{
    #[Sequentially([
        new NotNull(groups: ['edit']),
        new Type('string', groups: ['edit']),
    ])]
    public $route;

    #[Sequentially([
        new NotNull(groups: ['edit']),
        new Json(groups: ['edit']),
    ])]
    public $all_params;

    #[Sequentially([
        new NotNull(groups: ['edit']),
        new Json(groups: ['edit']),
    ])]
    public $content;
}
