<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\MailQueue;
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
        PageParamsService $pp,
        MailQueue $mail_queue,
        MailAddrUserService $mail_addr_user_service
    ):Response
    {
        $id = $request->request->get('id', '');

        error_log('id: ' . $id);

        $mollie_apikey = $db->fetchOne('select data->>\'apikey\'
            from ' . $pp->schema() . '.config
            where id = \'mollie\'', [], []);

        $mollie = new MollieApiClient();
        $mollie->setApiKey($mollie_apikey);

        $payment = $mollie->payments->get($id);
        $token = $payment->metadata->token;

        error_log('token: ' . $token);

        $mollie_payment = $db->fetchAssociative('select p.*, r.description, u.code
            from ' . $pp->schema() . '.mollie_payments p,
                ' . $pp->schema() . '.mollie_payment_requests r,
                ' . $pp->schema() . '.users u
            where p.request_id = r.id
                and u.id = p.user_id
                and p.token = ?',
            [$token], [\PDO::PARAM_STR]);

        error_log('mollie_payment: ' . json_encode($mollie_payment));

        if ($mollie_payment === false)
        {
            throw new NotFoundHttpException('Payment request not found');
        }

        error_log('MOLLIE_PAYMENT (db)');
        error_log(print_r($mollie_payment, true));
        error_log('PAYMENT isPaid()');
        error_log($payment->isPaid() ? 'TRUE' : 'FALSE');
        error_log('PAYMENT isCanceled()');
        error_log($payment->isCanceled() ? 'TRUE' : 'FALSE');
        error_log('PAYMENT isPending()');
        error_log($payment->isPending() ? 'TRUE' : 'FALSE');
        error_log('PAYMENT isOpen()');
        error_log($payment->isOpen() ? 'TRUE' : 'FALSE');
        error_log('PAYMENT STATUS');
        error_log($payment->status);

        if ($payment->isPaid())
        {
            $amount = strtr($mollie_payment['a'], '.', ',');
            $description = $mollie_payment['code'] . ' ' . $mollie_payment['description'];

            $vars = [
                'amount'        => $amount,
                'description'   => $description,
                'user_id'       => $payment['user_id'],
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
            'is_paid'           => $payment->isPaid(),
        ], ['token' => $token]);

        return new Response('');
    }
}
