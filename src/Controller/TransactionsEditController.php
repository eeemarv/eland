<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransactionsEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        LoggerInterface $logger,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        AccountRender $account_render,
        AlertService $alert_service,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        LinkRender $link_render,
        IntersystemsService $intersystems_service,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Transactions module not enabled.');
        }

        $errors = [];

        $intersystem_account_schemas = $intersystems_service->get_eland_accounts_schemas($pp->schema());

        $s_inter_schema_check = array_merge($intersystems_service->get_eland($pp->schema()),
            [$su->schema() => true]);

        $transaction = $db->fetchAssoc('select t.*
            from ' . $pp->schema() . '.transactions t
            where t.id = ?', [$id]);

        $inter_schema = false;

        if (isset($intersystem_account_schemas[$transaction['id_from']]))
        {
            $inter_schema = $intersystem_account_schemas[$transaction['id_from']];
        }
        else if (isset($intersystem_account_schemas[$transaction['id_to']]))
        {
            $inter_schema = $intersystem_account_schemas[$transaction['id_to']];
        }

        if ($inter_schema)
        {
            $inter_transaction = $db->fetchAssoc('select t.*
                from ' . $inter_schema . '.transactions t
                where t.transid = ?', [$transaction['transid']]);
        }
        else
        {
            $inter_transaction = false;
        }


        if (!$inter_transaction && ($transaction['real_from'] || $transaction['real_to']))
        {
            $alert_service->error('De omschrijving van een transactie
                naar een interSysteem dat draait op eLAS kan
                niet aangepast worden.');
            $link_render->redirect('transactions_show', $pp->ary(), ['id' => $id]);
        }

        if ($request->isMethod('POST'))
        {
            $description = trim($request->request->get('description', ''));

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (strlen($description) > 60)
            {
                $errors[] = 'De omschrijving mag maximaal 60 tekens lang zijn.';
            }

            if (!$description)
            {
                $errors[]= 'De omschrijving is niet ingevuld';
            }

            if (!count($errors))
            {
                $db->update($pp->schema() . '.transactions',
                    ['description' => $description],
                    ['id' => $id]);

                if ($inter_transaction)
                {
                    $db->update($inter_schema . '.transactions',
                        ['description' => $description],
                        ['id' => $inter_transaction['id']]);
                }

                $logger->info('Transaction description edited from "' . $transaction['description'] .
                    '" to "' . $description . '", transid: ' .
                    $transaction['transid'], ['schema' => $pp->schema()]);

                $alert_service->success('Omschrijving transactie aangepast.');

                $link_render->redirect('transactions_show', $pp->ary(), ['id' => $id]);
            }

            $alert_service->error($errors);
        }

        $heading_render->add('Omschrijving transactie aanpassen');
        $heading_render->fa('exchange');

        $out = '<i><ul>';
        $out .= '<li>Enkel Admins kunnen de omschrijving van ';
        $out .= 'een transactie aanpassen.</li>';
        $out .= '<li>Pas de omschrijving van een transactie ';
        $out .= 'enkel aan wanneer het echt noodzakelijk is! ';
        $out .= 'Dit om verwarring te vermijden.</li>';
        $out .= '<li>Transacties kunnen nooit ongedaan ';
        $out .= 'gemaakt worden. Doe een tegenboeking ';
        $out .= 'bij vergissing.</li>';
        $out .= '</ul></i>';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form  method="post" autocomplete="off">';

        // copied from "show a transaction"

        $out .= '<dl>';

        $out .= '<dt>Tijdstip</dt>';
        $out .= '<dd>';
        $out .= $date_format_service->get($transaction['created_at'], 'min', $pp->schema());
        $out .= '</dd>';

        $out .= '<dt>Transactie ID</dt>';
        $out .= '<dd>';
        $out .= $transaction['transid'];
        $out .= '</dd>';

        if ($transaction['real_from'])
        {
            $out .= '<dt>Van interSysteem account</dt>';
            $out .= '<dd>';

            if ($pp->is_admin())
            {
                $out .= $account_render->link($transaction['id_from'], $pp->ary());
            }
            else
            {
                $out .= $account_render->str($transaction['id_from'], $pp->schema());
            }

            $out .= '</dd>';

            $out .= '<dt>Van interSysteem gebruiker</dt>';
            $out .= '<dd>';
            $out .= '<span class="btn btn-default">';
            $out .= '<i class="fa fa-share-alt"></i></span> ';

            if ($inter_transaction)
            {
                if (isset($s_inter_schema_check[$inter_schema]))
                {
                    $out .= $account_render->inter_link($inter_transaction['id_from'],
                        $inter_schema, $pp->ary());
                }
                else
                {
                    $out .= $account_render->str($inter_transaction['id_from'],
                        $inter_schema);
                }
            }
            else
            {
                $out .= $transaction['real_from'];
            }

            $out .= '</dd>';
        }
        else
        {
            $out .= '<dt>Van gebruiker</dt>';
            $out .= '<dd>';
            $out .= $account_render->link($transaction['id_from'], $pp->ary());
            $out .= '</dd>';
        }

        if ($transaction['real_to'])
        {
            $out .= '<dt>Naar interSysteem account</dt>';
            $out .= '<dd>';

            if ($pp->is_admin())
            {
                $out .= $account_render->link($transaction['id_to'], $pp->ary());
            }
            else
            {
                $out .= $account_render->str($transaction['id_to'], $pp->schema());
            }

            $out .= '</dd>';

            $out .= '<dt>Naar interSysteem gebruiker</dt>';
            $out .= '<dd>';
            $out .= '<span class="btn btn-default"><i class="fa fa-share-alt"></i></span> ';

            if ($inter_transaction)
            {
                if (isset($s_inter_schema_check[$inter_schema]))
                {
                    $out .= $account_render->inter_link($inter_transaction['id_to'],
                        $inter_schema, $pp->ary());
                }
                else
                {
                    $out .= $account_render->str($inter_transaction['id_to'],
                        $inter_schema);
                }
            }
            else
            {
                $out .= $transaction['real_to'];
            }

            $out .= '</dd>';
        }
        else
        {
            $out .= '<dt>Naar gebruiker</dt>';
            $out .= '<dd>';
            $out .= $account_render->link($transaction['id_to'], $pp->ary());
            $out .= '</dd>';
        }

        $out .= '<dt>Waarde</dt>';
        $out .= '<dd>';
        $out .= $transaction['amount'] . ' ';
        $out .= $config_service->get('currency', $pp->schema());
        $out .= '</dd>';

        $out .= '<dt>Omschrijving</dt>';
        $out .= '<dd>';
        $out .= $transaction['description'];
        $out .= '</dd>';

        $out .= '</dl>';

        $out .= '<div class="form-group">';
        $out .= '<label for="description" class="control-label">';
        $out .= 'Nieuwe omschrijving</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-pencil"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="description" name="description" ';
        $out .= 'value="';
        $out .= $transaction['description'];
        $out .= '" required maxlength="60">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('transactions_show', $pp->ary(), ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Aanpassen" class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();
        $out .= '<input type="hidden" name="transid" ';
        $out .= 'value="';
        $out .= $transaction['transid'];
        $out .= '">';

        $out .= '</form>';
        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('transactions');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
