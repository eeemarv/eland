<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Routing\AccessLevel;
use App\Routing\MessagesSubModule;
use App\Routing\SchemaRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class MessagesExtendController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: MessagesSubModule::Expire,
    path: '/messages/{id}/extend/{days}',
    name: 'messages_extend',
    methods: ['GET', 'POST'],
    requirements: [
      'days'          => '%assert.id%',
    ],
  )]

  public function __invoke(
    int $id,
    int $days,
    Db $db,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {

    $message = MessagesShowController::get_message($db, $id, $pp->schema());

    if (!($su->is_owner($message['user_id']) || $pp->is_admin()))
    {
      throw $this->createAccessDeniedException('You have insufficient rights for this action.');
    }

    if (!isset($message['expires_at']))
    {
      $message['expires_at'] =  gmdate('Y-m-d H:i:s');
    }

    $expires_at = gmdate('Y-m-d H:i:s', strtotime($message['expires_at'] . ' UTC') + (86400 * $days));

    $m = [
      'expires_at'	=> $expires_at,
      'exp_user_warn'	=> 'f',
    ];

    if (!$db->update($pp->schema() . '.messages', $m, ['id' => $id]))
    {
      $this->addFlash('error', 'Fout: ' . $message['label']['offer_want_the'] . ' is niet verlengd.');

      return $this->redirectToRoute('messages_show', [
        ...$pp->ary(),
        'id' => $id,
      ]);
    }

    $this->addFlash('success', ucfirst($message['label']['offer_want_the']) . ' is verlengd.');

    return $this->redirectToRoute('messages_show', [
      ...$pp->ary(),
      'id' => $id,
    ]);
  }
}
