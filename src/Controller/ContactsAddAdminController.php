<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\AccessCnst;
use App\Queue\GeocodeQueue;
use App\Render\AccountRender;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;

class ContactsAddAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        ConfigService $config_service,
        LinkRender $link_render,
        AccountRender $account_render,
        AssetsService $assets_service,
        GeocodeQueue $geocode_queue,
        ItemAccessService $item_access_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp,
        HeadingRender $heading_render
    ):Response
    {
        $content = self::form(
            $request,
            0,
            true,
            $db,
            $alert_service,
            $form_token_service,
            $menu_service,
            $config_service,
            $link_render,
            $account_render,
            $assets_service,
            $geocode_queue,
            $item_access_service,
            $typeahead_service,
            $pp,
            $heading_render
        );

        return $this->render('base/navbar.html.twig', [
            'content'   => $content,
            'schema'    => $pp->schema(),
        ]);
    }

    public static function form(
        Request $request,
        int $user_id,
        bool $redirect_contacts,
        Db $db,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        ConfigService $config_service,
        LinkRender $link_render,
        AccountRender $account_render,
        AssetsService $assets_service,
        GeocodeQueue $geocode_queue,
        ItemAccessService $item_access_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp,
        HeadingRender $heading_render
    ):string
    {
        $errors = [];

        $account_code = $request->request->get('account_code', '');
        $id_type_contact = (int) $request->request->get('id_type_contact', '');
        $value = $request->request->get('value', '');
        $comments = $request->request->get('comments', '');
        $access = $request->request->get('access', '');

        if($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if ($pp->is_admin() && $redirect_contacts)
            {
               [$code] = explode(' ', trim($account_code));

                $user_id = $db->fetchColumn('select id
                    from ' . $pp->schema() . '.users
                    where code = ?', [$code]);

                if (!$user_id)
                {
                    $errors[] = 'Ongeldige Account Code.';
                }
            }

            if (!$value)
            {
                $errors[] = 'Vul waarde in!';
            }

            if (!$access)
            {
                $errors[] = 'Vul zichtbaarheid in.';
            }

            if (!isset(AccessCnst::ACCESS[$access]))
            {
                throw new BadRequestHttpException('Ongeldige waarde zichtbaarheid');
            }

            $abbrev_type = $db->fetchColumn('select abbrev
                from ' . $pp->schema() . '.type_contact
                where id = ?', [$id_type_contact]);

            if(!$abbrev_type)
            {
                throw new BadRequestHttpException('Ongeldig contact type!');
            }

            if ($abbrev_type === 'mail'
                && !filter_var($value, FILTER_VALIDATE_EMAIL))
            {
                $errors[] = 'Geen geldig E-mail adres';
            }

            if (strlen($value) > 130)
            {
                $errors[] = 'De waarde mag maximaal 130 tekens lang zijn.';
            }

            if (strlen($comments) > 50)
            {
                $errors[] = 'Commentaar mag maximaal 50 tekens lang zijn.';
            }

            $mail_type_id = $db->fetchColumn('select id
                from ' . $pp->schema() . '.type_contact
                where abbrev = \'mail\'');

            if ($id_type_contact === $mail_type_id)
            {
                $mailadr = $value;

                $mail_count = $db->fetchColumn('select count(c.*)
                    from ' . $pp->schema() . '.contact c, ' .
                        $pp->schema() . '.type_contact tc, ' .
                        $pp->schema() . '.users u
                    where c.id_type_contact = tc.id
                        and tc.abbrev = \'mail\'
                        and c.user_id = u.id
                        and u.status in (1, 2)
                        and u.id <> ?
                        and c.value = ?', [$user_id, $mailadr]);

                if ($mail_count && $pp->is_admin())
                {
                    $warning = 'Omdat deze gebruikers niet meer ';
                    $warning .= 'een uniek E-mail adres hebben zullen zij ';
                    $warning .= 'niet meer zelf hun paswoord kunnnen resetten ';
                    $warning .= 'of kunnen inloggen met ';
                    $warning .= 'E-mail adres. Zie ';
                    $warning .= $link_render->link_no_attr('status',
                        $pp->ary(), [], 'Status');

                    if ($mail_count == 1)
                    {
                        $warning_2 = 'Waarschuwing: E-mail adres ' . $mailadr;
                        $warning_2 .= ' bestaat al onder de actieve gebruikers.';
                    }
                    else if ($mail_count > 1)
                    {
                        $warning_2 = 'Waarschuwing: E-mail adres ' . $mailadr;
                        $warning_2 .= ' bestaat al ' . $mail_count;
                        $warning_2 .= ' maal onder de actieve gebruikers.';
                    }

                    $alert_service->warning($warning_2 . ' ' . $warning);
                }
                else if ($mail_count)
                {
                    $errors[] = 'Dit E-mail adres komt reeds voor onder
                        de actieve gebruikers.';
                }
            }

            if(!count($errors))
            {
                if ($abbrev_type === 'adr')
                {
                    $geocode_queue->cond_queue([
                        'adr'		=> $value,
                        'uid'		=> $user_id,
                        'schema'	=> $pp->schema(),
                    ], 0);
                }

                $insert_ary = [
                    'id_type_contact'		=> $id_type_contact,
                    'value'					=> $value,
                    'comments' 				=> $comments,
                    'user_id'				=> $user_id,
                    'access'                => $access,
                ];

                if ($db->insert($pp->schema() . '.contact', $insert_ary))
                {
                    $alert_service->success('Contact opgeslagen.');

                    if ($redirect_contacts)
                    {
                        $link_render->redirect('contacts', $pp->ary(), []);
                    }

                    $link_render->redirect('users_show', $pp->ary(),
                        ['id' => $user_id]);

                }
                else
                {
                    $alert_service->error('Fout bij het opslaan');
                }
            }
            else
            {
                $alert_service->error($errors);
            }
        }

        $tc = [];

        $rs = $db->prepare('select id, name, abbrev
            from ' . $pp->schema() . '.type_contact');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $tc[$row['id']] = $row;

            if ($id_type_contact)
            {
                continue;
            }

            $id_type_contact = $row['id'];
        }

        $assets_service->add(['contacts_edit.js']);

        $abbrev = $tc[$id_type_contact]['abbrev'];

        $heading_render->add('Contact toevoegen');

        if ($pp->is_admin() && !$redirect_contacts)
        {
            $heading_render->add(' voor ');
            $heading_render->add_raw($account_render->link($user_id, $pp->ary()));
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        if ($pp->is_admin() && $redirect_contacts)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="account_code" class="control-label">Voor</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon" id="fcode_addon">';
            $out .= '<span class="fa fa-user"></span></span>';
            $out .= '<input type="text" class="form-control" id="account_code" name="account_code" ';

            $out .= 'data-typeahead="';
            $out .= $typeahead_service->ini($pp->ary())
                ->add('accounts', ['status' => 'active'])
                ->add('accounts', ['status' => 'inactive'])
                ->add('accounts', ['status' => 'ip'])
                ->add('accounts', ['status' => 'im'])
                ->add('accounts', ['status' => 'extern'])
                ->str([
                    'filter'        => 'accounts',
                    'newuserdays'   => $config_service->get('newuserdays', $pp->schema()),
                ]);
            $out .= '" ';

            $out .= 'placeholder="Account Code" ';
            $out .= 'value="';
            $out .= $account_code;
            $out .= '" required>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '<div class="form-group">';
        $out .= '<label for="id_type_contact" class="control-label">Type</label>';
        $out .= '<select name="id_type_contact" id="id_type_contact" ';
        $out .= 'class="form-control" required>';

        foreach ($tc as $id_tc => $type)
        {
            $out .= '<option value="';
            $out .= $id_tc;
            $out .= '" ';
            $out .= 'data-abbrev="';
            $out .= $type['abbrev'];
            $out .= '" ';
            $out .= $id_tc === $id_type_contact ? ' selected="selected"' : '';
            $out .= '>';
            $out .= $type['name'];
            $out .= '</option>';
        }

        $out .= "</select>";
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="value" class="control-label">';
        $out .= 'Waarde</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon" id="value_addon">';
        $out .= '<i class="fa fa-';
        $out .= ContactsEditAdminController::FORMAT[$abbrev]['fa'] ?? 'circle-o';
        $out .= '"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="value" name="value" ';
        $out .= 'value="';
        $out .= $value;
        $out .= '" required disabled maxlength="130" ';
        $out .= 'data-contacts-format="';
        $out .= htmlspecialchars(json_encode(ContactsEditAdminController::FORMAT));
        $out .= '">';
        $out .= '</div>';
        $out .= '<p id="contact-explain">';

        $out .= '</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="comments" class="control-label">';
        $out .= 'Commentaar</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-comment-o"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="comments" name="comments" ';
        $out .= 'value="';
        $out .= $comments;
        $out .= '" maxlength="50">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $item_access_service->get_radio_buttons('access', $access, 'contacts_add');

        if ($redirect_contacts)
        {
           $out .= $link_render->btn_cancel('contacts', $pp->ary(), []);
        }
        else
        {
            $out .= $link_render->btn_cancel('users_show', $pp->ary(),
                ['id' => $user_id]);
        }

        $out .= '&nbsp;';

        $out .= '<input type="submit" value="Opslaan" ';
        $out .= 'name="zend" class="btn btn-success btn-lg">';

        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set($redirect_contacts ? 'contacts' : 'users');

        return $out;
    }
}
