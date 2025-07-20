<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Command\Index\IndexContactFormCommand;
use App\Email\Index\ContactConfirm\EmailIndexContactConfirmMessage;
use App\Form\Type\Index\IndexContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class IndexContactLinkSentController extends AbstractController
{
  #[Route(
    '/contact/link_sent',
    name: 'index_contact_link_sent',
    methods: ['GET'],
  )]

  public function __invoke():Response
  {
    return $this->render('index/contact_link_sent.html.twig', []);
  }
}
