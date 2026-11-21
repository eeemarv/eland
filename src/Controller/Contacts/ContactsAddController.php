<?php declare(strict_types=1);

namespace App\Controller\Contacts;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Command\Contacts\ContactsCommand;
use App\Form\Type\Contacts\ContactsType;
use App\Queue\GeocodeQueue;
use App\Repository\ContactRepository;
use App\Routing\AccessLevel;
use App\Routing\SchemaRoute;
use App\Routing\UsersSubModule;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class ContactsAddController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Contacts,
    is_self: false,
    path: '/contacts/add',
    name: 'contacts_add',
    methods: ['GET', 'POST'],
  )]

  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Contacts,
    is_self: false,
    path: '/users/{user_id}/contacts/add',
    name: 'users_contacts_add',
    methods: ['GET', 'POST'],
    requirements: [
      'user_id'       => '%assert.id%',
    ],
  )]

  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: UsersSubModule::Contacts,
    is_self: true,
    path: '/users/contacts/add',
    name: 'users_contacts_add_self',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    bool $_is_self,
    ContactRepository $contact_repository,
    GeocodeQueue $geocode_queue,
    PageParamsService $pp,
    SessionUserService $su,
    int|null $user_id = null,
  ):Response
  {
    $redirect_contacts = $pp->route() === 'contacts_add';

    if ($_is_self)
    {
      $user_id = $su->id();
    }

    $command = new ContactsCommand();

    if ($user_id)
    {
      $command->user_id = $user_id;
    }

    $form_options = [
      'validation_groups' => ['add'],
      'user_id_enabled'   => $redirect_contacts,
    ];

    $form = $this->createForm(ContactsType::class,
      $command, $form_options);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $user_id = $command->user_id;
      $value = $command->value;

      $contact_repository->insert(
        value: $value,
        comments: $command->comments,
        contact_type_id: $command->contact_type_id,
        access: $command->access,
        user_id: $user_id,
        created_by: $su->is_master() ? null : $su->id(),
        schema: $pp->schema_o(),
      );

      $contact_type = $contact_repository->get_contact_type(
        id: $command->contact_type_id,
        schema: $pp->schema_o(),
      );

      if ($contact_type['abbrev'] === 'adr')
      {
        $geocode_queue->cond_queue([
          'adr'		=> $command->value,
          'uid'		=> $user_id,
          'schema'	=> $pp->schema(),
        ], 0);
      }

      if ($contact_type['abbrev'] === 'mail')
      {
        $mail_count = $contact_repository->get_mail_count_except_for_user(
          email_address: $value,
          user_id: $user_id,
          schema: $pp->schema_o(),
        );

        if ($mail_count && $pp->is_admin())
        {
          $warning = 'Omdat deze gebruikers niet meer ';
          $warning .= 'een uniek E-mail adres hebben zullen zij ';
          $warning .= 'niet meer zelf hun paswoord kunnnen resetten ';
          $warning .= 'of kunnen inloggen met ';
          $warning .= 'E-mail adres.';

          if ($mail_count == 1)
          {
            $warning_2 = 'Waarschuwing: E-mail adres ' . $value;
            $warning_2 .= ' bestaat al onder de actieve gebruikers.';
          }
          else if ($mail_count > 1)
          {
            $warning_2 = 'Waarschuwing: E-mail adres ' . $value;
            $warning_2 .= ' bestaat al ' . $mail_count;
            $warning_2 .= ' maal onder de actieve gebruikers.';
          }

          $this->addFlash('warning', $warning_2 . ' ' . $warning);
        }
      }

      $this->addFlash('success', 'Contact opgeslagen.');

      if ($redirect_contacts)
      {
        return $this->redirectToRoute('contacts', $pp->ary());
      }

      return $this->redirectToRoute('users_show', [
        ...$pp->ary(),
        'id' => $user_id,
      ]);
    }

    return $this->render('contacts/contacts_add.html.twig', [
      'form'              => $form->createView(),
      'is_self'           => $_is_self,
      'user_id'           => $user_id,
      'redirect_contacts' => $redirect_contacts,
    ]);
  }
}
