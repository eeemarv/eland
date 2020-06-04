<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpFoundation\Request;

class TypeaheadUsernamesController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $thumbprint,
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $usernames = [];

        $st = $db->prepare('select name
            from ' . $pp->schema() . '.users
            order by name asc');

        $st->execute();

        while ($row = $st->fetch())
        {
            if (empty($row['name']))
            {
                continue;
            }

            $usernames[] = $row['name'];
        }

        $crc = (string) crc32(json_encode($usernames));

        $typeahead_service->set_thumbprint('usernames', $pp->ary(), [], $crc);

        return $this->json($usernames);
    }
}
