<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class categories_edit
{
    public function match(Request $request, app $app, int $id):Response
    {
        $cats = [];

        $rs = $app['db']->prepare('select *
            from ' . $app['tschema'] . '.categories
            order by fullname');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $cats[$row['id']] = $row;
        }

        $child_count_ary = [];

        foreach ($cats as $cat)
        {
            $child_count_ary[$cat['id_parent']]++;
        }

        $cat = $cats[$id];

        if ($request->isMethod('POST'))
        {
            $cat['name'] = $request->request->get('name', '');
            $cat['id_parent'] = (int) $request->request->get('id_parent', 0);
            $cat['leafnote'] = $cat['id_parent'] === 0 ? 0 : 1;

            if (!$cat['name'])
            {
                $app['alert']->error('Vul naam in!');
            }
            else if (($cat['stat_msgs_wanted'] + $cat['stat_msgs_offers']) && !$cat['leafnote'])
            {
                $app['alert']->error('Hoofdcategoriën kunnen
                    geen berichten bevatten.');
            }
            else if ($cat['leafnote'] && $child_count_ary[$id])
            {
                $app['alert']->error('Subcategoriën kunnen
                    geen categoriën bevatten.');
            }
            else if ($token_error = $app['form_token']->get_error())
            {
                $app['alert']->error($token_error);
            }
            else
            {
                $prefix = '';

                if ($cat['id_parent'])
                {
                    $prefix .= $app['db']->fetchColumn('select name
                        from ' . $app['tschema'] . '.categories
                        where id = ?', [$cat['id_parent']]) . ' - ';
                }

                $cat['fullname'] = $prefix . $cat['name'];
                unset($cat['id']);

                if ($app['db']->update($app['tschema'] . '.categories', $cat, ['id' => $id]))
                {
                    $app['alert']->success('Categorie aangepast.');
                    $app['db']->executeUpdate('update ' . $app['tschema'] . '.categories
                        set fullname = ? || \' - \' || name
                        where id_parent = ?', [$cat['name'], $id]);

                    $app['link']->redirect('categories', $app['pp_ary'], []);
                }

                $app['alert']->error('Categorie niet aangepast.');
            }
        }

        $parent_cats = [0 => '-- Hoofdcategorie --'];

        $rs = $app['db']->prepare('select id, name
            from ' . $app['tschema'] . '.categories
            where leafnote = 0
            order by name');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $parent_cats[$row['id']] = $row['name'];
        }

        $id_parent = $cat['id_parent'] ?? 0;

        $app['heading']->add('Categorie aanpassen : ');
        $app['heading']->add($cat['name']);
        $app['heading']->fa('clone');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-clone"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name" ';
        $out .= 'value="';
        $out .= $cat["name"] ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="id_parent" class="control-label">';
        $out .= 'Hoofdcategorie of deelcategorie van</label>';
        $out .= '<select class="form-control" id="id_parent" name="id_parent">';
        $out .= $app['select']->get_options($parent_cats, (string) $id_parent);
        $out .= '</select>';
        $out .= '</div>';

        $out .= $app['link']->btn_cancel('categories', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Opslaan" ';
        $out .= 'name="zend" class="btn btn-primary">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('categories');

        return $app['tpl']->get();
    }
}
