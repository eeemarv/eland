<?php declare(strict_types=1);

namespace App\Command\Cms;

use Symfony\Component\Validator\Constraints\ExpressionSyntax;
use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

class CmsEditCommand
{
    #[Sequentially(constraints: [
        new NotNull(),
        new Type(type: 'string'),
    ])]
    public $route;

    #[ExpressionSyntax(
        allowedVariables: ['0', '1']
    )]
    public $route_en;

    #[ExpressionSyntax(
        allowedVariables: ['admin', 'user', 'guest', 'anonymous']
    )]
    public $role;

    #[ExpressionSyntax(
        allowedVariables: ['0', '1']
    )]
    public $role_en;

    #[Sequentially(constraints: [
        new NotNull(),
        new Json(),
    ])]
    public $all_params;

    #[Sequentially(constraints: [
        new NotNull(),
        new Json(),
    ])]
    public $content;
}
