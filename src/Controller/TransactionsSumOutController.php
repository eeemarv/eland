<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TransactionsSumOutController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $days,
        Db $db,
        PageParamsService $pp
    ):Response
    {
        return TransactionsSumInController::calc(
            $request,
            $days,
            false,
            $db,
            $pp
        );
    }
}
