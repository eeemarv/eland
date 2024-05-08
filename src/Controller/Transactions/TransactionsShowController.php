<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Cache\ConfigCache;
use App\Cache\SystemsCache;
use App\Cnst\MessageTypeCnst;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\TransactionRepository;
use App\Service\AssetsService;
use App\Service\DateFormatService;
use App\Service\IntersystemsService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TransactionsShowController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/transactions/{id}',
        name: 'transactions_show',
        methods: ['GET'],
        priority: 10,
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'module'        => 'transactions',
        ],
    )]

    public function __invoke(
        int $id,
        Db $db,
        TransactionRepository $transaction_repository,
        ConfigCache $config_cache,
        DateFormatService $date_format_service,
        AccountRender $account_render,
        AssetsService $assets_service,
        IntersystemsService $intersystems_service,
        LinkRender $link_render,
        SystemsCache $systems_cache,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_cache->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Transactions module not enabled.');
        }

        $currency = $config_cache->get_str('transactions.currency.name', $pp->schema());
        $service_stuff_enabled = $config_cache->get_bool('transactions.fields.service_stuff.enabled', $pp->schema());

        $intersystem_account_schemas = $intersystems_service->get_eland_accounts_schemas($pp->schema());
        $eland_intersystem_ary = $intersystems_service->get_eland($pp->schema());

        $su_intersystem_ary = $intersystems_service->get_eland($su->schema());
        $su_intersystem_ary[$su->schema()] = true;

        $transaction = $transaction_repository->get($id, $pp->schema());

        $inter_schema = false;

        if (isset($intersystem_account_schemas[$transaction['id_from']]))
        {
            $inter_schema = $intersystem_account_schemas[$transaction['id_from']];
        }
        else if (isset($intersystem_account_schemas[$transaction['id_to']]))
        {
            $inter_schema = $intersystem_account_schemas[$transaction['id_to']];
        }

        $inter_transactions_enabled = false;

        if ($inter_schema)
        {
            $inter_transaction = $db->fetchAssociative('select t.*
                from ' . $inter_schema . '.transactions t
                where t.transid = ?',
                [$transaction['transid']], [\PDO::PARAM_STR]);

            $inter_transactions_enabled = $config_cache->get_bool('transactions.enabled', $inter_schema);
        }
        else
        {
            $inter_transaction = false;
        }

        $next_id = $transaction_repository->get_next_id($id, $pp->schema());
        $prev_id = $transaction_repository->get_prev_id($id, $pp->schema());

        $real_to = $transaction['real_to'] ? true : false;
        $real_from = $transaction['real_from'] ? true : false;

        $intersystem_trans = ($real_from || $real_to) && $config_cache->get_intersystem_en($pp->schema());

        $out = '<div class="panel panel-';
        $out .= $intersystem_trans ? 'warning' : 'default';
        $out .= ' printview">';
        $out .= '<div class="panel-heading">';

        $out .= '<dl>';

        $out .= '<dt>Tijdstip</dt>';
        $out .= '<dd>';
        $out .= $date_format_service->get($transaction['created_at'], 'min', $pp->schema());
        $out .= '</dd>';

        $out .= '<dt>Transactie ID</dt>';
        $out .= '<dd>';
        $out .= $transaction['transid'];
        $out .= '</dd>';

        if ($real_from)
        {
            $out .= '<dt>Van interSysteem Account (in dit Systeem)</dt>';
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

            $out .= '<dt>Van Account in het andere Systeem</dt>';
            $out .= '<dd>';
            $out .= '<span class="btn btn-default">';
            $out .= '<i class="fa fa-share-alt"></i></span> ';

            if ($inter_transaction)
            {
                if (isset($su_intersystem_ary[$inter_schema]))
                {
                    $user_from = $account_render->inter_link($inter_transaction['id_from'],
                        $inter_schema, $su);
                }
                else
                {
                    $user_from = $account_render->str($inter_transaction['id_from'],
                        $inter_schema);
                }
            }
            else
            {
                $user_from = $transaction['real_from'];
            }

            $out .= $user_from;

            $out .= '</dd>';
        }
        else
        {
            $out .= '<dt>Van Account</dt>';
            $out .= '<dd>';
            $out .= $account_render->link($transaction['id_from'], $pp->ary());
            $out .= '</dd>';
        }

        if ($real_to)
        {
            $out .= '<dt>Naar interSysteem Account (in dit Systeem)</dt>';
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

            $out .= '<dt>Naar Account in het andere Systeem</dt>';
            $out .= '<dd>';
            $out .= '<span class="btn btn-default">';
            $out .= '<i class="fa fa-share-alt"></i></span> ';

            if ($inter_transaction)
            {
                if (isset($su_intersystem_ary[$inter_schema]))
                {
                    $user_to = $account_render->inter_link($inter_transaction['id_to'],
                        $inter_schema, $su);
                }
                else
                {
                    $user_to = $account_render->str($inter_transaction['id_to'],
                        $inter_schema);
                }
            }
            else
            {
                $user_to = $transaction['real_to'];
            }

            $out .= $user_to;

            $out .= '</dd>';
        }
        else
        {
            $out .= '<dt>Naar Account</dt>';
            $out .= '<dd>';
            $out .= $account_render->link($transaction['id_to'], $pp->ary());
            $out .= '</dd>';
        }

        $out .= '<dt>Waarde</dt>';
        $out .= '<dd>';
        $out .= $transaction['amount'] . ' ';
        $out .= $currency;
        $out .= '</dd>';

        $out .= '<dt>Omschrijving</dt>';
        $out .= '<dd>';
        $out .= htmlspecialchars($transaction['description'], ENT_QUOTES);
        $out .= '</dd>';

        if ($service_stuff_enabled)
        {
            $out .= '<dt>Diensten / spullen</dt>';
            $out .= '<dd>';

            if (isset($transaction['service_stuff']))
            {
                $se_st = MessageTypeCnst::SERVICE_STUFF_TPL_ARY[$transaction['service_stuff']];
                $out .= '<span class="btn btn-' . $se_st['btn_class'] . '">';
                $out .= $se_st['label'];
                $out .= '</span>';
            }
            else
            {
                $out .= '<span class="text-danger"><b><em>* Onbepaald *</em></b></span>';
            }

            $out .= '</dd>';
        }

        $out .= '</dl>';

        if ($intersystem_trans)
        {
            $out .= '<div class="row">';
            $out .= '<div class="col-md-12">';
            $out .= '<h2>';
            $out .= 'Dit is een interSysteem transactie ';
            $out .= $real_from ? 'vanuit' : 'naar';
            $out .= ' een Account in ander Systeem';
            $out .= '</h2>';
            $out .= '<p>';
            $out .= 'Een interSysteem transactie bestaat ';
            $out .= 'altijd uit twee gekoppelde transacties, die ';
            $out .= 'elks binnen hun eigen Systeem plaatsvinden, ';
            $out .= 'elks uitgedrukt in de eigen tijdmunt, maar met ';
            $out .= 'gelijke tijdwaarde in beide transacties. ';
            $out .= 'De zogenaamde interSysteem Accounts ';
            $out .= '(in stippellijn) ';
            $out .= 'doen dienst als intermediair.';
            $out .= '</p>';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '<div class="row">';

            $out .= '<div class="col-md-6">';
            $out .= '<div class="thumbnail">';
            $out .= '<img src="';

            if ($real_from)
            {
                $out .= $assets_service->get('there-from-inter.png');
            }
            else
            {
                $out .= $assets_service->get('here-to-inter.png');
            }

            $out .= '">';
            $out .= '</div>';
            $out .= '<div class="caption">';
            $out .= '<ul>';
            $out .= '<li>';
            $out .= '<strong>Acc-1</strong> ';
            $out .= 'Het Account in ';
            $out .= $real_from ? 'het andere' : 'dit';
            $out .= ' Systeem dat de ';
            $out .= 'transactie initiëerde. ';
            $out .= '(';

            if ($real_from)
            {
                $out .= '<span class="btn btn-default">';
                $out .= '<i class="fa fa-share-alt"></i></span> ';
                $out .= $user_from;
            }
            else
            {
                $out .= $account_render->link($transaction['id_from'], $pp->ary());
            }

            $out .= ')';
            $out .= '</li>';
            $out .= '<li>';
            $out .= '<strong>Tr-1</strong> ';

            if ($real_from)
            {
                $str = 'De transactie in het andere ';
                $str .= 'Systeem uitgedrukt ';
                $str .= 'in de eigen tijdmunt.';

                if ($inter_transaction
                    && isset($eland_intersystem_ary[$inter_schema])
                    && isset($su_intersystem_ary[$inter_schema])
                    && $inter_transactions_enabled)
                {
                    if ($su->schema() === $inter_schema)
                    {
                        $out .= $link_render->link_no_attr('transactions_show', [
                                'system'	    => $su->system(),
                                'role_short'	=> $su->role_short(),
                            ], ['id' => $inter_transaction['id']], $str);
                    }
                    else
                    {
                        $out .= $link_render->link_no_attr('transactions_show', [
                            'system'	    => $systems_cache->get_system($inter_schema),
                            'role_short'	=> 'g',
                            'os'            => $su->system(),
                        ], ['id' => $inter_transaction['id']], $str);
                    }
                }
                else
                {
                    $out .= $str;
                }
            }
            else
            {
                $out .= 'De transactie in dit ';
                $out .= 'Systeem uitgedrukt ';
                $out .= 'in de eigen tijdmunt';
                $out .= ' (';
                $out .= $transaction['amount'];
                $out .= ' ';
                $out .= $currency;
                $out .= ').';
            }

            $out .= '</li>';
            $out .= '<li>';
            $out .= '<strong>iAcc-1</strong> ';

            if ($real_from)
            {
                $out .= 'Het interSysteem Account van dit Systeem in het ';
                $out .= 'andere Systeem.';
            }
            else
            {
                $out .= 'Het interSysteem Account van het andere Systeem ';
                $out .= 'in dit Systeem. (';

                if ($pp->is_admin())
                {
                    $out .= $account_render->link($transaction['id_to'],
                        $pp->ary());
                }
                else
                {
                    $out .= $account_render->str($transaction['id_to'],
                        $pp->schema());
                }

                $out .= ')';
            }

            $out .= '</li>';
            $out .= '</ul>';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '<div class="col-md-6">';
            $out .= '<div class="thumbnail">';
            $out .= '<img src="';

            if ($real_from)
            {
                $out .= $assets_service->get('here-from-inter.png');
            }
            else
            {
                $out .= $assets_service->get('there-to-inter.png');
            }

            $out .= '">';
            $out .= '</div>';
            $out .= '<div class="caption bg-warning">';
            $out .= '<ul>';
            $out .= '<li>';
            $out .= '<strong>iAcc-2</strong> ';

            if ($real_from)
            {
                $out .= 'Het interSysteem Account van ';
                $out .= 'het andere Systeem in dit ';
                $out .= 'Systeem. ';
                $out .= '(';

                if ($pp->is_admin())
                {
                    $out .= $account_render->link($transaction['id_from'],
                        $pp->ary());
                }
                else
                {
                    $out .= $account_render->str($transaction['id_from'],
                        $pp->schema());
                }

                $out .= ')';
            }
            else
            {
                $out .= 'Het interSysteem Account van dit Systeem in het ';
                $out .= 'andere Systeem.';
            }

            $out .= '</li>';
            $out .= '<li>';
            $out .= '<strong>Tr-2</strong> ';

            if ($real_from)
            {
                $out .= 'De transactie in dit Systeem uitgedrukt ';
                $out .= 'in de eigen tijdmunt ';
                $out .= '(';
                $out .= $transaction['amount'] . ' ';
                $out .= $currency;
                $out .= ') ';
                $out .= 'met gelijke tijdwaarde als Tr-1.';
            }
            else
            {
                $str = 'De transactie in het andere ';
                $str .= 'Systeem uitgedrukt ';
                $str .= 'in de eigen tijdmunt ';
                $str .= 'met gelijke tijdwaarde als Tr-1.';

                if ($inter_transaction
                    && isset($eland_intersystem_ary[$inter_schema])
                    && isset($su_intersystem_ary[$inter_schema])
                    && $inter_transactions_enabled)
                {
                    if ($su->schema() === $inter_schema)
                    {
                        $out .= $link_render->link_no_attr('transactions_show', [
                                'system'	    => $su->system(),
                                'role_short'	=> $su->role_short(),
                            ], ['id' => $inter_transaction['id']], $str);
                    }
                    else
                    {
                        $out .= $link_render->link_no_attr('transactions_show', [
                            'system'	    => $systems_cache->get_system($inter_schema),
                            'role_short'	=> 'g',
                            'os'            => $su->system(),
                        ], ['id' => $inter_transaction['id']], $str);
                    }
                }
                else
                {
                    $out .= $str;
                }
            }

            $out .= '</li>';
            $out .= '<li>';
            $out .= '<strong>Acc-2</strong> ';

            if ($real_from)
            {
                $out .= 'Het bestemmings Account in dit Systeem ';
                $out .= '(';
                $out .= $account_render->link($transaction['id_to'], $pp->ary());
                $out .= ').';
            }
            else
            {
                $out .= 'Het bestemmings Account in het andere ';
                $out .= 'Systeem ';
                $out .= '(';
                $out .= '<span class="btn btn-default">';
                $out .= '<i class="fa fa-share-alt"></i></span> ';
                $out .= $user_to;
                $out .= ').';
            }

            $out .= '</li>';
            $out .= '</ul>';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '</div>';
        }

        $out .= '</div></div>';

        return $this->render('transactions/transactions_show.html.twig', [
            'content'           => $out,
            'transaction'       => $transaction,
            'is_inter'          => $intersystem_trans,
            'inter_transaction' => $inter_transaction,
            'inter_schema'      => $inter_schema,
            'id'                => $id,
            'prev_id'           => $prev_id,
            'next_id'           => $next_id,
        ]);
    }
}
