<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadIntersystemMailAccountsController extends AbstractController
{
    public function __invoke(
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        if(!$pp->is_admin())
        {
            return $this->json(['error' => 'No access.'], 403);
        }

        $accounts = $db->fetchAll(
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
            order by u.id asc'
        );

        $crc = (string) crc32(json_encode($accounts));

        $typeahead_service->set_thumbprint('intersystem_mail_accounts', $pp->ary(), [], $crc);

        return $this->json($accounts);
    }
}