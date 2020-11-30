<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\MailQueue;
use App\Service\ConfigService;
use App\Service\MailAddrUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use Doctrine\DBAL\Connection as Db;
use Mollie\Api\MollieApiClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MollieWebhookController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        ConfigService $config_service,
        PageParamsService $pp,
        MailQueue $mail_queue,
        MailAddrUserService $mail_addr_user_service
    ):Response
    {
        $id = $request->request->get('id', '');

        $mollie_apikey = $config_service->get_str('mollie.apikey', $pp->schema());

        $mollie = new MollieApiClient();
        $mollie->setApiKey($mollie_apikey);

        $payment = $mollie->payments->get($id);
        $token = $payment->metadata->token;

        $mollie_payment = $db->fetchAssociative('select p.*, r.description, u.code
            from ' . $pp->schema() . '.mollie_payments p,
                ' . $pp->schema() . '.mollie_payment_requests r,
                ' . $pp->schema() . '.users u
            where p.request_id = r.id
                and u.id = p.user_id
                and p.token = ?',
            [$token], [\PDO::PARAM_STR]);

        if ($mollie_payment === false)
        {
            throw new NotFoundHttpException('Payment request not found');
        }

        if ($payment->isPaid())
        {
            $amount = strtr($mollie_payment['amount'], '.', ',');
            $description = $mollie_payment['code'] . ' ' . $mollie_payment['description'];

            $vars = [
                'amount'        => $amount,
                'description'   => $description,
                'user_id'       => $mollie_payment['user_id'],
            ];

            $mail_queue->queue([
                'schema'	=> $pp->schema(),
                'template'	=> 'mollie/is_paid',
                'vars'		=> $vars,
                'to'		=> $mail_addr_user_service->get($mollie_payment['user_id'], $pp->schema()),
            ], 8500);
        }

        $db->update($pp->schema() . '.mollie_payments',[
            'mollie_status'     => $payment->status,
            'is_paid'           => $payment->isPaid() ? 't' : 'f',
        ], ['token' => $token], [\PDO::PARAM_STR]);

        return new Response('');
    }
}
