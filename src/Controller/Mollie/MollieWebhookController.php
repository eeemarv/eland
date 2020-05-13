<?php declare(strict_types=1);

namespace App\Controller\Mollie;

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

        $mollie_apikey = $db->fetchColumn('select data->>\'apikey\'
            from ' . $pp->schema() . '.config
            where id = \'mollie\'');

        $mollie = new MollieApiClient();
        $mollie->setApiKey($mollie_apikey);

        $payment = $mollie->payments->get($id);
        $token = $payment->metadata->token;

        $mollie_payment = $db->fetchAssoc('select p.*, r.description, u.code
            from ' . $pp->schema() . '.mollie_payments p,
                ' . $pp->schema() . '.mollie_payment_requests r,
                ' . $pp->schema() . '.users u
            where p.request_id = r.id
                and u.id = p.user_id
                and p.token = ?', [$token]);

        if (!$mollie_payment)
        {
            throw new NotFoundHttpException('Payment request not found');
        }

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
