<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Command\Index\IndexContactFormCommand;
use App\Email\Index\ContactConfirm\EmailIndexContactConfirmMessage;
use App\Form\Type\Index\IndexContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class IndexContactController extends AbstractController
{
  #[Route(
    '/contact',
    name: 'index_contact',
    methods: ['GET', 'POST'],
    priority: 40,
  )]

  public function __invoke(
    Request $request,
    MessageBusInterface $bus,
  ):Response
  {
    $session = $request->getSession();
    if ($session instanceof Session)
    {
      $flash_bag = $session->getFlashBag();
      if ($flash_bag->peek('content'))
      {
        /** no form, just a flash message */
        return $this->render('index/contact.html.twig', []);
      }
    }

    $command = new IndexContactFormCommand();

    $form_options = [
      'validation_groups' => ['send']
    ];

    $form = $this->createForm(IndexContactFormType::class, $command, $form_options);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid()
    )
    {
      $command = $form->getData();

      $email_address = strtolower($command->email_address);

      $m_confirm = new EmailIndexContactConfirmMessage(
        to: new Address($email_address),
        message: $command->message,
        agent: $request->headers->get('User-Agent'),
        ip: $request->getClientIp(),
      );

      $bus->dispatch($m_confirm);

      $this->addFlash('content', 'open_email');

      return $this->redirectToRoute('index_contact');
    }

    return $this->render('index/contact.html.twig', [
      'form' => $form,
    ]);
  }
}
