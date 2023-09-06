<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TypeaheadUsernamesController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/typeahead-usernames/{thumbprint}',
        name: 'typeahead_usernames',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        string $thumbprint,
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $cached = $typeahead_service->get_cached_response_body($thumbprint, $pp, []);

        if ($cached !== false)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
        }

        $usernames = [];

        $stmt = $db->prepare('select name
            from ' . $pp->schema() . '.users
            order by name asc');

        $res = $stmt->executeQuery();

        while ($row = $res->fetchAssociative())
        {
            if (empty($row['name']))
            {
                continue;
            }

            $usernames[] = $row['name'];
        }

        $response_body = json_encode($usernames);
        $typeahead_service->store_response_body($response_body, $pp, []);
        return new Response($response_body, 200, ['Content-Type' => 'application/json']);
    }
}
