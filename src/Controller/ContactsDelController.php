<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use controller\contacts_edit;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;

class ContactsDelController extends AbstractController
{
    public function contacts_del_admin(
        Request $request,
        app $app,
        int $id,
        Db $db
    ):Response
    {
        $contact = contacts_edit::get_contact($db, $id,  $app['pp_schema']);

        return self::form($request, $app, $contact['id_user'], $id, true, $db);
    }

    public static function form(
        Request $request,
        app $app,
        int $user_id,
        int $id,
        bool $redirect_contacts,
        Db $db
    ):Response
    {
        $contact = contacts_edit::get_contact($db, $id,  $app['pp_schema']);

        if ($user_id !== $contact['id_user'])
        {
            throw new BadRequestHttpException(
                'Contact ' . $id . ' behoort niet tot gebruiker ' . $user_id);
        }

        if ($request->isMethod('GET'))
        {
            if ($contact['abbrev'] === 'mail'
                && $app['user_cache']->is_active_user($user_id, $app['pp_schema']))
            {
                $count_mail = $db->fetchColumn('select count(c.*)
                    from ' . $app['pp_schema'] . '.contact c
                    where c.id_type_contact = ?
                        and c.id_user = ?', [
                            $contact['id_type_contact'],
                            $user_id]);

                if ($count_mail === 1)
                {
                    if ($app['pp_admin'])
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
            $errors = [];

            if ($error_token = $app['form_token']->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $db->delete($app['pp_schema'] . '.contact', ['id' => $id]);

                $app['alert']->success('Contact verwijderd.');

                if ($redirect_contacts)
                {
                    $app['link']->redirect('contacts', $app['pp_ary'], []);
                }
                else
                {
                    $app['link']->redirect('users_show', $app['pp_ary'],
                        ['id' => $user_id]);
                }
            }

            $app['alert']->error($error_token);
        }

        if ($app['pp_admin'])
        {
            $app['heading']->add('Contact verwijderen voor ');
            $app['heading']->add_raw($app['account']->link($user_id, $app['pp_ary']));
            $app['heading']->add('?');
        }
        else
        {
            $app['heading']->add('Contact verwijderen?');
        }

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

        $out .= $app['item_access']->get_label($contact['access']);

        $out .= '</dd>';
        $out .= '</dl>';

        $out .= '<form method="post" class="form-horizontal">';

        if ($redirect_contacts)
        {
            $out .= $app['link']->btn_cancel('contacts', $app['pp_ary'], []);
        }
        else
        {
            $out .= $app['link']->btn_cancel('users_show', $app['pp_ary'],
                ['id' => $user_id]);
        }

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set($redirect_contacts ? 'contacts' : 'users');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}