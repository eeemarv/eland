<?php declare(strict_types=1);

namespace App\Util;

use Silex\Route as silex_route;

class route extends silex_route
{
    use \Silex\Route\SecurityTrait;
}