<?php declare(strict_types=1);

namespace App\Controller\Contacts;

use App\Command\Contacts\ContactsCommand;
use App\Form\Type\Contacts\ContactsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Queue\GeocodeQueue;
use App\Service\AlertService;
use App\Repository\ContactRepository;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\Routing\Annotation\Route;

class ContactsEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/contacts/{id}/edit',
        name: 'contacts_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'user_id'               => 0,
            'contact_id'            => 0,
            'redirect_contacts'     => true,
            'is_self'               => false,
            'module'                => 'users',
            'sub_module'            => 'contacts',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/{user_id}/contacts/{contact_id}/edit',
        name: 'users_contacts_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'user_id'       => '%assert.id%',
            'contact_id'    => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'id'                    => 0,
            'redirect_contacts'     => false,
            'is_self'               => false,
            'module'                => 'users',
            'sub_module'            => 'contacts',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/contacts/{contact_id}/edit',
        name: 'users_contacts_edit_self',
        methods: ['GET', 'POST'],
        requirements: [
            'contact_id'    => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'id'                    => 0,
            'user_id'               => 0,
            'redirect_contacts'     => false,
            'is_self'               => true,
            'module'                => 'users',
            'sub_module'            => 'contacts',
        ],
    )]

    public function __invoke(
        Request $request,
        int $user_id,
        int $contact_id,
        int $id,
        bool $redirect_contacts,
        bool $is_self,
        ContactRepository $contact_repository,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su,
        GeocodeQueue $geocode_queue
    ):Response
    {
        $id = $contact_id ?: $id;

        $contact = $contact_repository->get($id, $pp->schema());

        if ($is_self)
        {
            $user_id = $su->id();
        }
        else if ($redirect_contacts)
        {
            $user_id = $contact['user_id'];
        }

        if (!$user_id)
        {
            throw new BadRequestHttpException('No user_id');
        }

        if ($user_id !== $contact['user_id'])
        {
            throw new BadRequestHttpException(
                'Contact ' . $id . ' does not belong to user ' . $user_id);
        }

        $command = new ContactsCommand();
        $command->id = $id;
        $command->user_id = $contact['user_id'];
        $command->contact_type_id = $contact['id_type_contact'];
        $command->value = $contact['value'];
        $command->comments = $contact['comments'];
        $command->access = $contact['access'];

        $form_options = [
            'validation_groups'     => ['edit'],
        ];

        $form = $this->createForm(ContactsType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $contact_repository->update($command, $pp->schema());

            $user_id = $command->user_id;
            $value = $command->value;

            $contact_type = $contact_repository->get_contact_type($command->contact_type_id, $pp->schema());

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
                $mail_count = $contact_repository->get_mail_count_except_for_user($value, $user_id, $pp->schema());

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

                    $alert_service->warning($warning_2 . ' ' . $warning);
                }
            }

            $alert_service->success('Contact aangepast.');

            if ($redirect_contacts)
            {
                return $this->redirectToRoute('contacts', $pp->ary());
            }

            return $this->redirectToRoute('users_show', [
                ...$pp->ary(),
                'id' => $user_id,
            ]);
        }

        return $this->render('contacts/contacts_edit.html.twig', [
            'form'      => $form->createView(),
            'contact'   => $contact,
            'is_self'   => $is_self,
            'user_id'   => $user_id,
            'redirect_contacts' => $redirect_contacts,
        ]);
    }
}
