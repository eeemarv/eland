<?php declare(strict_types=1);

namespace controller;

use util\app;
use controller\contacts_edit;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class contacts_del
{
    public function users_contacts_del(Request $request, app $app, int $user_id, int $contact_id):Response
    {
        $contact = contacts_edit::get_contact_for_users_route(
            $contact_id, $user_id, $app['s_id'],
            $app['s_admin'], $app['db'], $app['tschema']);

        return $this->admin($request, $app, $contact_id);
    }

    public function contacts_del_admin(Request $request, app $app, int $id):Response
    {
        $contact = contacts_edit::get_contact_for_admin_route(
            $id, $app['db'], $app['tschema']);

        $abbrev = $app['db']->fetchColumn('select tc.abbrev
            from ' . $app['tschema'] . '.type_contact tc
            where tc.id = ?', [$contact['id_type_contact']]);

        $user_id = $contact['id_user'];

        $user = $app['user_cache']->get($user_id, $app['tschema']);

        if ($request->isMethod('GET'))
        {
            if ($abbrev === 'mail'
                && ($user['status'] === 1 || $user['status'] === 2))
            {
                $count_mail = $app['db']->fetchColumn('select count(c.*)
                    from ' . $app['tschema'] . '.contact c
                    where c.id_type_contact = ?
                        and c.id_user = ?', [
                            $contact['id_type_contact'],
                            $user_id]);

                if ($count_mail === 1)
                {
                    if ($app['s_admin'])
                    {
                        $app['alert']->warning(
                            'Waarschuwing: dit is het enige E-mail adres
                            van een actieve gebruiker');
                    }
                    else
                    {
                        $app['alert']->warning(
                            'Waarschuwing: dit is je enige E-mail adres.');
                    }
                }
            }
        }


        if ($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
            }
            else
            {
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
        $out .= $abbrev;
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
