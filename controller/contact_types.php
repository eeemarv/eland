<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class contact_types
{
    const PROTECTED = ['mail', 'gsm', 'tel', 'adr', 'web'];

    public function get(Request $request, app $app):Response
    {
        $types = $app['db']->fetchAll('select *
            from ' . $app['tschema'] . '.type_contact tc');

        $contact_count = [];

        $rs = $app['db']->prepare('select distinct id_type_contact, count(id)
            from ' . $app['tschema'] . '.contact
            group by id_type_contact');
        $rs->execute();

        while($row = $rs->fetch())
        {
            $contact_count[$row['id_type_contact']] = $row['count'];
        }

        $app['btn_top']->add('contact_types_add', $app['pp_ary'],
            [], 'Contact type toevoegen');

        $app['heading']->add('Contact types');
        $app['heading']->fa('circle-o-notch');

        $out = '<div class="panel panel-default printview">';

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-striped table-hover ';
        $out .= 'table-bordered footable" data-sort="false">';
        $out .= '<tr>';
        $out .= '<thead>';
        $out .= '<th>Naam</th>';
        $out .= '<th>Afkorting</th>';
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
                $out .= $app['link']->link_no_attr('contact_types_edit', $app['pp_ary'],
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
                $out .= $app['link']->link_no_attr('contact_types_edit', $app['pp_ary'],
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
                $out .= $app['link']->link_fa('contact_types_del', $app['pp_ary'],
                    ['id' => $t['id']], 'Verwijderen',
                    ['class' => 'btn btn-danger'],
                    'times');
            }

            $out .= '</td>';

            $out .= '<td>';

            if ($count)
            {
                $out .= $app['link']->link_no_attr('contacts', $app['pp_ary'],
                    ['f' => ['abbrev' => $t['abbrev']]], $count);
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

        $app['tpl']->add($out);

        return $app['tpl']->get($request);
    }
}