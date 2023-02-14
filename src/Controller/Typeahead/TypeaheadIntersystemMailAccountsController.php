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
class TypeaheadIntersystemMailAccountsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/typeahead-intersystem-mail-accounts/{thumbprint}',
        name: 'typeahead_intersystem_mail_accounts',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'transactions',
        ],
    )]

    public function __invoke(
        string $thumbprint,
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

        $accounts = $db->fetchAllAssociative(
            'select u.code as c,
                u.name as n,
                extract(epoch from u.adate) as a,
                u.status as s,
                \'mail\' as api
            from ' . $pp->schema() . '.users u,
               ' . $pp->schema() . '.letsgroups l
            where u.status = 7
                and l.apimethod = \'mail\'
                and l.localletscode = u.code
            order by u.id asc', [], []
        );

        $data = json_encode($accounts);
        $typeahead_service->set_thumbprint($thumbprint, $data, $pp, []);
        return new Response($data, 200, ['Content-Type' => 'application/json']);
    }
}
