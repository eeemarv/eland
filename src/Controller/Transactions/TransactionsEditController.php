<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Cnst\BulkCnst;
use App\Cnst\MessageTypeCnst;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class TransactionsEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/transactions/{id}/edit',
        name: 'transactions_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'transactions',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        LoggerInterface $logger,
        FormTokenService $form_token_service,
        AccountRender $account_render,
        AlertService $alert_service,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        LinkRender $link_render,
        IntersystemsService $intersystems_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Transactions module not enabled.');
        }

        $errors = [];

        $service_stuff_enabled = $config_service->get_bool('transactions.fields.service_stuff.enabled', $pp->schema());

        $intersystem_account_schemas = $intersystems_service->get_eland_accounts_schemas($pp->schema());

        $su_intersystem_ary = $intersystems_service->get_eland($su->schema());
        $su_intersystem_ary[$su->schema()] = true;

        $transaction = $db->fetchAssociative('select t.*
            from ' . $pp->schema() . '.transactions t
            where t.id = ?', [$id]);

        $description = $transaction['description'];
        $service_stuff = $transaction['service_stuff'] ?? 'null-service-stuff';

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
            $inter_transaction = $db->fetchAssociative('select t.*
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
            $service_stuff = $request->request->get('service_stuff', '');

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

            if ($service_stuff_enabled)
            {
                if (!$service_stuff)
                {
                    $errors[] = 'Selecteer diensten of spullen';
                }
                else if (!in_array($service_stuff, ['service', 'stuff', 'null-service-stuff']))
                {
                    throw new BadRequestHttpException('Wrong value for service_stuff: ' . $service_stuff);
                }

                if ($service_stuff === 'null-service-stuff')
                {
                    $service_stuff = null;
                }
            }

            if (!count($errors))
            {
                $update_ary = [
                    'description'   => $description,
                ];

                if ($service_stuff_enabled)
                {
                    $update_ary['service_stuff'] = $service_stuff;
                }

                $db->update($pp->schema() . '.transactions',
                    $update_ary, ['id' => $id]);

                if ($inter_transaction)
                {
                    $db->update($inter_schema . '.transactions',
                        $update_ary,
                        ['id' => $inter_transaction['id']]);
                }

                $logger->info('#transaction_edit, old: ' . json_encode($transaction) .
                    ' to new: ' . json_encode($update_ary),
                    ['schema' => $pp->schema()]);

                $alert_service->success('Transactie aangepast.');
                $link_render->redirect('transactions_show', $pp->ary(), ['id' => $id]);
            }

            $alert_service->error($errors);
        }

        $out = '<div class="panel panel-info">';
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
                if (isset($su_intersystem_ary[$inter_schema]))
                {
                    $out .= $account_render->inter_link($inter_transaction['id_from'],
                        $inter_schema, $su);
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
                if (isset($su_intersystem_ary[$inter_schema]))
                {
                    $out .= $account_render->inter_link($inter_transaction['id_to'],
                        $inter_schema, $su);
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
        $out .= $config_service->get_str('transactions.currency.name', $pp->schema());
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
        $out .= $description;
        $out .= '" required maxlength="60">';
        $out .= '</div>';
        $out .= '</div>';

        if ($service_stuff_enabled)
        {
            $out .= '<div class="form-group">';
            $out .= '<div class="custom-radio">';

            foreach (MessageTypeCnst::SERVICE_STUFF_TPL_ARY as $key => $render_data)
            {
                $out .= strtr(BulkCnst::TPL_RADIO_INLINE,[
                    '%name%'    => 'service_stuff',
                    '%value%'   => $key,
                    '%attr%'    => ' required' . ($service_stuff === $key ? ' checked' : ''),
                    '%label%'   => '<span class="btn btn-' . $render_data['btn_class'] . '">' . $render_data['label'] . '</span>',
                ]);
            }

            $out .= '</div>';
            $out .= '</div>';
        }

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

        return $this->render('transactions/transactions_edit.html.twig', [
            'content'   => $out,
        ]);
    }
}
