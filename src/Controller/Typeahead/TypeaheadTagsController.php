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
class TypeaheadTagsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/typeahead-tags/{tag_type}/{thumbprint}',
        name: 'typeahead_tags',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
            'tag_type'      => '%assert.tag_type%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        string $thumbprint,
        string $tag_type,
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $cached = $typeahead_service->get_cached_data($thumbprint, $pp, []);

        if ($cached !== false)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
        }

        $tags = [];

        $stmt = $db->prepare('select id, txt, txt_color, bg_color
            from ' . $pp->schema() . '.tags
            where tag_type = ?');

        $stmt->bindValue(1, $tag_type, \PDO::PARAM_STR);

        $res = $stmt->executeQuery();

        while ($row = $res->fetchAssociative())
        {
            $tags[] = $row;
        }

        $data = json_encode($tags);
        $typeahead_service->set_thumbprint($thumbprint, $data, $pp, []);
        return new Response($data, 200, ['Content-Type' => 'application/json']);
    }
}
