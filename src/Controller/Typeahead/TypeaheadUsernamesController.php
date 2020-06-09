<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadUsernamesController extends AbstractController
{
    public function __invoke(
        string $thumbprint,
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $cached = $typeahead_service->get_cached_data($thumbprint, []);

        if (isset($cached) && $cached)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
        }

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

        $typeahead_service->set_thumbprint(
            TypeaheadService::GROUP_USERS,
            $thumbprint, $usernames, []);

        return $this->json($usernames);
    }
}
