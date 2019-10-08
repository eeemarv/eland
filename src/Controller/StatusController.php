<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class StatusController extends AbstractController
{
    public function status(app $app, Db $db):Response
    {
        $status_msgs = false;

        $non_unique_mail = $db->fetchAll('select c.value, count(c.*)
            from ' . $app['pp_schema'] . '.contact c, ' .
                $app['pp_schema'] . '.type_contact tc, ' .
                $app['pp_schema'] . '.users u
            where c.id_type_contact = tc.id
                and tc.abbrev = \'mail\'
                and c.id_user = u.id
                and u.status in (1, 2)
            group by value
            having count(*) > 1');

        if (count($non_unique_mail))
        {
            $st = $db->prepare('select id_user
                from ' . $app['pp_schema'] . '.contact c
                where c.value = ?');

            foreach ($non_unique_mail as $key => $ary)
            {
                $st->bindValue(1, $ary['value']);
                $st->execute();

                while ($row = $st->fetch())
                {
                    $non_unique_mail[$key]['users'][$row['id_user']] = true;
                }
            }

            $status_msgs = true;
        }

        //

        $non_unique_letscode = $db->fetchAll('select letscode, count(*)
            from ' . $app['pp_schema'] . '.users
            where letscode <> \'\'
            group by letscode
            having count(*) > 1');

        if (count($non_unique_letscode))
        {
            $st = $db->prepare('select id
                from ' . $app['pp_schema'] . '.users
                where letscode = ?');

            foreach ($non_unique_letscode as $key => $ary)
            {
                $st->bindValue(1, $ary['letscode']);
                $st->execute();

                while ($row = $st->fetch())
                {
                    $non_unique_letscode[$key]['users'][$row['id']] = true;
                }
            }

            $status_msgs = true;
        }

        //

        $non_unique_name = $db->fetchAll('select name, count(*)
            from ' . $app['pp_schema'] . '.users
            where name <> \'\'
            group by name
            having count(*) > 1');

        if (count($non_unique_name))
        {
            $st = $db->prepare('select id
                from ' . $app['pp_schema'] . '.users
                where name = ?');

            foreach ($non_unique_name as $key => $ary)
            {
                $st->bindValue(1, $ary['name']);
                $st->execute();

                while ($row = $st->fetch())
                {
                    $non_unique_name[$key]['users'][$row['id']] = true;
                }
            }

            $status_msgs = true;
        }

        //

        $unvalid_mail = $db->fetchAll('select c.id, c.value, c.id_user
            from ' . $app['pp_schema'] . '.contact c, ' .
                $app['pp_schema'] . '.type_contact tc
            where c.id_type_contact = tc.id
                and tc.abbrev = \'mail\'
                and c.value !~ \'^[A-Za-z0-9!#$%&*+/=?^_`{|}~.-]+@[A-Za-z0-9.-]+[.][A-Za-z]+$\'');

        //
        $no_mail = array();

        $st = $db->prepare(' select u.id
            from ' . $app['pp_schema'] . '.users u
            where u.status in (1, 2)
                and not exists (select c.id
                    from ' . $app['pp_schema'] . '.contact c, ' .
                        $app['pp_schema'] . '.type_contact tc
                    where c.id_user = u.id
                        and c.id_type_contact = tc.id
                        and tc.abbrev = \'mail\')');

        $st->execute();

        while ($row = $st->fetch())
        {
            $no_mail[] = $row['id'];
            $status_msgs = true;
        }

        $empty_letscode = $db->fetchAll('select id
            from ' . $app['pp_schema'] . '.users
            where status in (1, 2) and letscode = \'\'');

        $empty_name = $db->fetchAll('select id
            from ' . $app['pp_schema'] . '.users
            where name = \'\'');

        if ($unvalid_mail || $empty_letscode || $empty_name)
        {
            $status_msgs = true;
        }

        $no_msgs_users = $db->fetchAll('select id, letscode, name, saldo, status
            from ' . $app['pp_schema'] . '.users u
            where status in (1, 2)
                and not exists (select 1
                    from ' . $app['pp_schema'] . '.messages m
                    where m.id_user = u.id)');

        if (count($no_msgs_users))
        {
            $status_msgs = true;
        }

        $app['heading']->add('Status');
        $app['heading']->fa('exclamation-triangle');

        $out = '';

        if ($status_msgs)
        {
            $out .= '<div class="panel panel-danger">';

            $out .= '<ul class="list-group">';

            if (count($non_unique_mail))
            {
                $out .= '<li class="list-group-item">';

                if (count($non_unique_mail) == 1)
                {
                    $out .= 'Een E-mail adres komt meer dan ';
                    $out .= 'eens voor onder de actieve accounts ';
                    $out .= 'in dit Systeem. ';
                    $out .= 'Gebruikers met dit E-mail adres ';
                    $out .= 'kunnen niet inloggen met E-mail adres. ';
                }
                else
                {
                    $out .= 'Meerdere E-mail adressen komen meer ';
                    $out .= 'dan eens voor onder de actieve ';
                    $out .= 'Accounts in dit Systeem. ';
                    $out .= 'Gebruikers met een E-mail adres ';
                    $out .= 'dat meer dan eens voorkomt, ';
                    $out .= 'kunnen niet inloggen met E-mail adres.';
                }

                $out .= '<ul>';

                foreach ($non_unique_mail as $ary)
                {
                    $out .= '<li>';
                    $out .= $ary['value'] . ' (' . $ary['count'] . '): ';

                    $user_ary = array();

                    foreach($ary['users'] as $user_id => $dummy)
                    {
                        $user_ary[] = $app['account']->link($user_id, $app['pp_ary']);
                    }

                    $out .= implode(', ', $user_ary);
                    $out .= '</li>';
                }

                $out .= '</ul>';
                $out .= '</li>';
            }

            if (count($non_unique_letscode))
            {
                $out .= '<li class="list-group-item">';

                if (count($non_unique_letscode) == 1)
                {
                    $out .= 'Een Account Code komt meer ';
                    $out .= 'dan eens voor in dit Systeem. ';
                    $out .= 'Actieve gebruikers met deze ';
                    $out .= 'accounts kunnen niet inloggen met Account Code ';
                    $out .= 'en kunnen geen transacties ';
                    $out .= 'doen of transacties ontvangen. ';
                }
                else
                {
                    $out .= 'Meerdere Account Codes komen ';
                    $out .= 'meer dan eens voor in dit Systeem. ';
                    $out .= 'Gebruikers met deze accounts ';
                    $out .= 'kunnen niet inloggen met de Account Code ';
                    $out .= 'en kunnen geen transacties ';
                    $out .= 'doen of transacties ontvangen.';
                }

                $out .= '<ul>';
                foreach ($non_unique_letscode as $ary)
                {
                    $out .= '<li>';
                    $out .= $ary['letscode'] . ' (' . $ary['count'] . '): ';

                    $user_ary = array();

                    foreach($ary['users'] as $user_id => $dummy)
                    {
                        $user_ary[] = $app['account']->link($user_id, $app['pp_ary']);
                    }

                    $out .= implode(', ', $user_ary);
                    $out .= '</li>';
                }
                $out .= '</ul>';
                $out .= '</li>';
            }

            if (count($non_unique_name))
            {
                $out .= '<li class="list-group-item">';

                if (count($non_unique_name) == 1)
                {
                    $out .= 'Een gebruikersnaam komt meer ';
                    $out .= 'dan eens voor in dit Systeem. ';
                    $out .= 'Actieve gebruikers met deze ';
                    $out .= 'gebruikersnaam kunnen niet ';
                    $out .= 'inloggen met gebruikersnaam. ';
                }
                else
                {
                    $out .= 'Meerdere gebruikersnamen komen meer dan eens voor in dit Systeem.';
                    $out .= 'Actieve gebruikers met een gebruikersnaam die meer dan eens voorkomt, kunnen niet inloggen met gebruikersnaam.';
                }

                $out .= '<ul>';

                foreach ($non_unique_name as $ary)
                {
                    $out .= '<li>';
                    $out .= $ary['name'] . ' (' . $ary['count'] . '): ';

                    $user_ary = array();

                    foreach($ary['users'] as $user_id => $dummy)
                    {
                        $user_ary[] = $app['account']->link($user_id, $app['pp_ary']);
                    }

                    $out .= implode(', ', $user_ary);
                    $out .= '</li>';
                }
                $out .= '</ul>';
                $out .= '</li>';
            }

            if (count($unvalid_mail))
            {
                $out .= '<li class="list-group-item">';
                if (count($unvalid_mail) == 1)
                {
                    $out .= 'Dit Systeem bevat een fout ';
                    $out .= 'geformateerd E-mail adres. ';
                    $out .= 'Pas het aan of verwijder het!';
                }
                else
                {
                    $out .= 'Dit Systeem bevat fout geformateerde ';
                    $out .= 'E-mail adressen. ';
                    $out .= 'Verwijder deze of pas deze aan!';
                }

                $out .= '<ul>';

                foreach ($unvalid_mail as $ary)
                {
                    $out .= '<li>';
                    $out .= $ary['value'] .  ' ';

                    $out .= $app['link']->link('contacts', $app['pp_ary'],
                        ['edit' => $ary['id']], 'Aanpassen',
                        ['class' => 'btn btn-default']);

                    $out .= ' ';

                    $out .= $app['link']->link('contacts', $app['pp_ary'],
                        ['del' => $ary['id']], 'Verwijderen',
                        ['class' => 'btn btn-danger btn-xs']);
                    $out .= ' : ';

                    $out .= $app['account']->link($ary['id_user'], $app['pp_ary']);

                    $out .= '</li>';
                }

                $out .= '</ul>';
                $out .= '</li>';
            }

            if (count($no_mail))
            {
                $out .= '<li class="list-group-item">';
                if (count($no_mail) == 1)
                {
                    $out .= 'Eén actieve gebruiker heeft geen E-mail adres.';
                }
                else
                {
                    $out .= count($no_mail);
                    $out .= ' actieve gebruikers hebben geen E-mail adres.';
                }

                $out .= '<ul>';
                foreach ($no_mail as $user_id)
                {
                    $out .= '<li>';
                    $out .= $app['account']->link($user_id, $app['pp_ary']);
                    $out .= '</li>';
                }

                $out .= '</ul>';
                $out .= '</li>';
            }

            if (count($empty_name))
            {
                $out .= '<li class="list-group-item">';
                if (count($empty_name) == 1)
                {
                    $out .= 'Eén gebruiker heeft geen gebruikersnaam.';
                }
                else
                {
                    $out .= count($empty_name) . ' gebruikers hebben geen gebruikersnaam.';
                }

                $out .= '<ul>';
                foreach ($empty_name as $ary)
                {
                    $out .= '<li>';
                    $out .= $app['account']->link($ary['id'], $app['pp_ary']);
                    $out .= '</li>';
                }

                $out .= '</ul>';
                $out .= '</li>';
            }

            if (count($empty_letscode))
            {
                $out .= '<li class="list-group-item">';
                if (count($empty_letscode) == 1)
                {
                    $out .= 'Eén actieve gebruiker heeft geen Account Code.';
                }
                else
                {
                    $out .= count($empty_letscode) . ' actieve gebruikers hebben geen Account Code.';
                }

                $out .= '<ul>';
                foreach ($empty_letscode as $ary)
                {
                    $out .= '<li>';
                    $out .= $app['account']->link($ary['id'], $app['pp_ary']);
                    $out .= '</li>';
                }

                $out .= '</ul>';
                $out .= '</li>';
            }

            if (count($no_msgs_users))
            {
                $out .= '<li class="list-group-item">';
                if (count($no_msgs_users) == 1)
                {
                    $out .= 'Eén actieve gebruiker heeft geen vraag of aanbod.';
                }
                else
                {
                    $out .= count($no_msgs_users) . ' actieve gebruikers hebben geen vraag of aanbod.';
                }

                $out .= '<ul>';

                $currency = $app['config']->get('currency', $app['pp_schema']);

                foreach ($no_msgs_users as $u)
                {
                    $out .= '<li>';
                    $out .= $app['account']->link($u['id'], $app['pp_ary']);
                    $out .= $u['status'] == 2 ? ' <span class="text-danger">Uitstapper</span>' : '';
                    $out .= ', saldo: ';
                    $out .= $u['saldo'];
                    $out .= ' ';
                    $out .= $currency;
                    $out .= '</li>';
                }

                $out .= '</ul>';
                $out .= '</li>';
            }

            $out .= '</ul>';
            $out .= '</div>';
        }
        else
        {
            $out .= '<div class="panel panel-info">';
            $out .= '<div class="panel-body">';
            $out .= '<p>Geen bijzonderheden</p>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $app['menu']->set('status');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
