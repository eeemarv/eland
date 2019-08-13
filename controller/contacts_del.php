<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class contacts_del
{
    public function users(Request $request, app $app, int $user_id, int $contact_id):Response
    {
        return $this->admin($request, $app, $contact_id);
    }

    public function admin(Request $request, app $app, int $id):Response
    {
        if (!($user_id = $app['db']->fetchColumn('select c.id_user
            from ' . $app['tschema'] . '.contact c
            where c.id = ?', [$id])))
        {
            $app['alert']->error('Het contact bestaat niet.');
            $app['link']->redirect('contacts', $app['pp_ary'], []);
        }

        $contact = $app['db']->fetchAssoc('select c.*, tc.abbrev
            from ' . $app['tschema'] . '.contact c, ' .
                $app['tschema'] . '.type_contact tc
            where c.id = ?
                and tc.id = c.id_type_contact', [$id]);

        $owner_id = $contact['id_user'];

        $owner = $app['user_cache']->get($owner_id, $app['tschema']);

        if ($contact['abbrev'] == 'mail'
            && ($owner['status'] == 1 || $owner['status'] == 2))
        {
            if ($app['db']->fetchColumn('select count(c.*)
                from ' . $app['tschema'] . '.contact c, ' .
                    $app['tschema'] . '.type_contact tc
                where c.id_type_contact = tc.id
                    and c.id_user = ?
                    and tc.abbrev = \'mail\'', [$user_id]) == 1)
            {
                $app['alert']->warning(
                    'Waarschuwing: dit is het enige E-mail adres
                    van een actieve gebruiker');
            }
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
                $app['link']->redirect('contacts_del', $app['pp_ary'],
                    ['id' => $id]);
            }

            if ($app['db']->delete($app['tschema'] . '.contact', ['id' => $id]))
            {
                $app['alert']->success('Contact verwijderd.');
            }
            else
            {
                $app['alert']->error('Fout bij verwijderen van het contact.');
            }

            $app['link']->redirect('contacts', $app['pp_ary'], []);
        }

        $contact = $app['db']->fetchAssoc('select tc.abbrev,
                c.value, c.comments, c.flag_public
            from ' . $app['tschema'] . '.type_contact tc, ' .
                $app['tschema'] . '.contact c
            where c.id_type_contact = tc.id
                and c.id = ?', [$id]);

        $app['heading']->add('Contact verwijderen?');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<dl>';

        $out .= '<dt>Gebruiker</dt>';
        $out .= '<dd>';

        $out .= $app['account']->link($user_id, $app['pp_ary']);

        $out .= '</dd>';

        $out .= '<dt>Type</dt>';
        $out .= '<dd>';
        $out .= $contact['abbrev'];
        $out .= '</dd>';
        $out .= '<dt>Waarde</dt>';
        $out .= '<dd>';
        $out .= $contact['value'];
        $out .= '</dd>';
        $out .= '<dt>Commentaar</dt>';
        $out .= '<dd>';
        $out .= $contact['comments'] ?: '<i class="fa fa-times"></i>';
        $out .= '</dd>';
        $out .= '<dt>Zichtbaarheid</dt>';
        $out .= '<dd>';

        $out .= $app['item_access']->get_label_flag_public($contact['flag_public']);

        $out .= '</dd>';
        $out .= '</dl>';

        $out .= '<form method="post" class="form-horizontal">';

        $out .= $app['link']->btn_cancel('contacts', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('contacts');

        return $app['tpl']->get();
    }
}
