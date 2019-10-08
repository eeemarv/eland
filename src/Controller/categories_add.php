<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class categories_add
{
    public function categories_add(Request $request, app $app):Response
    {
        $cat = [];

        if ($request->isMethod('POST'))
        {
            $cat['name'] = $request->request->get('name', '');
            $cat['id_parent'] = (int) $request->request->get('id_parent', 0);
            $cat['leafnote'] = $cat['id_parent'] === 0 ? 0 : 1;

            if (trim($cat['name']) === '')
            {
                $errors[] = 'Vul naam in!';
            }

            if (strlen($cat['name']) > 40)
            {
                $errors[] = 'De naam mag maximaal 40 tekens lang zijn.';
            }

            if ($token_error = $app['form_token']->get_error())
            {
                $errors[] = $token_error;
            }

            if (!count($errors))
            {
                $cat['cdate'] = gmdate('Y-m-d H:i:s');
                $cat['id_creator'] = $app['s_master'] ? 0 : $app['s_id'];
                $cat['fullname'] = '';

                if ($cat['leafnote'])
                {
                    $cat['fullname'] .= $app['db']->fetchColumn('select name
                        from ' . $app['pp_schema'] . '.categories
                        where id = ?', [(int) $cat['id_parent']]);
                    $cat['fullname'] .= ' - ';
                }

                $cat['fullname'] .= $cat['name'];

                if ($app['db']->insert($app['pp_schema'] . '.categories', $cat))
                {
                    $app['alert']->success('Categorie toegevoegd.');
                    $app['link']->redirect('categories', $app['pp_ary'], []);
                }

                $app['alert']->error('Categorie niet toegevoegd.');
            }
            else
            {
                $app['alert']->error($errors);
            }
        }

        $parent_cats = [0 => '-- Hoofdcategorie --'];

        $rs = $app['db']->prepare('select id, name
            from ' . $app['pp_schema'] . '.categories
            where leafnote = 0 order by name');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $parent_cats[$row['id']] = $row['name'];
        }

        $id_parent = $cat['id_parent'] ?? 0;

        $app['heading']->add('Categorie toevoegen');
        $app['heading']->fa('clone');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form  method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-clone"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name" ';
        $out .= 'value="';
        $out .= $cat['name'] ?? '';
        $out .= '" required maxlength="40">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="id_parent" class="control-label">';
        $out .= 'Hoofdcategorie of deelcategorie van</label>';
        $out .= '<select name="id_parent" id="id_parent" class="form-control">';
        $out .= $app['select']->get_options($parent_cats, (string) ($id_parent ?? 0));
        $out .= '</select>';
        $out .= '</div>';

        $out .= $app['link']->btn_cancel('categories', $app['pp_ary'], []);
        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Toevoegen" ';
        $out .= 'class="btn btn-success btn-lg">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('categories');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
