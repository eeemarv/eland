<?php declare(strict_types=1);

namespace App\Controller\ContactTypes;

use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Routing\Annotation\Route;

class ContactTypesController extends AbstractController
{
    const PROTECTED = ['mail', 'gsm', 'tel', 'adr', 'web'];

    #[Route(
        '/{system}/{role_short}/contact-types',
        name: 'contact_types',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'contact_types',
        ],
    )]

    public function __invoke(
        Db $db,
        BtnTopRender $btn_top_render,
        LinkRender $link_render,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $types = $db->fetchAllAssociative('select *
            from ' . $pp->schema() . '.type_contact tc', [], []);

        $contact_count = [];

        $rs = $db->prepare('select distinct id_type_contact, count(id)
            from ' . $pp->schema() . '.contact
            group by id_type_contact');
        $rs->execute();

        while($row = $rs->fetch())
        {
            $contact_count[$row['id_type_contact']] = $row['count'];
        }

        $btn_top_render->add('contact_types_add', $pp->ary(),
            [], 'Contact type toevoegen');

        $out = '<div class="panel panel-default printview">';

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-striped table-hover ';
        $out .= 'table-bordered footable" data-sort="false">';
        $out .= '<tr>';
        $out .= '<thead>';
        $out .= '<th>Afk.</th>';
        $out .= '<th>Naam</th>';
        $out .= '<th data-hide="phone">Verwijderen</th>';
        $out .= '<th data-hide="phone">Contacten</th>';
        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        foreach($types as $t)
        {
            $count = $contact_count[$t['id']] ?? 0;

            $protected = in_array($t['abbrev'], self::PROTECTED);

            $out .= '<tr>';

            $out .= '<td>';

            if ($protected)
            {
                $out .= htmlspecialchars($t['abbrev'],ENT_QUOTES) . '*';
            }
            else
            {
                $out .= $link_render->link_no_attr('contact_types_edit', $pp->ary(),
                    ['id' => $t['id']], $t['abbrev']);
            }

            $out .= '</td>';

            $out .= '<td>';

            if ($protected)
            {
                $out .= htmlspecialchars($t['name'],ENT_QUOTES) . '*';
            }
            else
            {
                $out .= $link_render->link_no_attr('contact_types_edit', $pp->ary(),
                    ['id' => $t['id']], $t['name']);
            }

            $out .= '</td>';

            $out .= '<td>';

            if ($protected || $count)
            {
                $out .= '&nbsp;';
            }
            else
            {
                $out .= $link_render->link_fa('contact_types_del', $pp->ary(),
                    ['id' => $t['id']], 'Verwijderen',
                    ['class' => 'btn btn-danger'],
                    'times');
            }

            $out .= '</td>';

            $out .= '<td>';

            if ($count)
            {
                $out .= $link_render->link_no_attr('contacts', $pp->ary(),
                    ['f' => ['abbrev' => $t['abbrev']]], (string) $count);
            }
            else
            {
                $out .= '&nbsp;';
            }

            $out .= '</td>';

            $out .= '</tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';
        $out .= '</div></div>';

        $out .= '<p>Kunnen niet verwijderd worden: ';
        $out .= 'contact types waarvan contacten ';
        $out .= 'bestaan en beschermde contact types (*).</p>';

        $menu_service->set('contact_types');

        return $this->render('contact_types/contact_types.html.twig', [
            'content'   => $out,
        ]);
    }
}
