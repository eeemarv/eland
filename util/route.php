<?php declare(strict_types=1);

namespace util;

use Silex\Route as silex_route;

class route extends silex_route
{
    use \Silex\Route\SecurityTrait;
}