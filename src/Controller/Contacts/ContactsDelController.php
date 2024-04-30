<?php declare(strict_types=1);

namespace App\Controller\Contacts;

use App\Cache\UserCache;
use App\Command\Contacts\ContactsCommand;
use App\Form\Type\Contacts\ContactsDelType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Service\AlertService;
use App\Repository\ContactRepository;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ContactsDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/contacts/{id}/del',
        name: 'contacts_del_admin',
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
        '/{system}/{role_short}/users/{user_id}/contacts/{contact_id}/del',
        name: 'users_contacts_del_admin',
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
        '/{system}/{role_short}/users/contacts/{contact_id}/del',
        name: 'users_contacts_del',
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
        UserCache $user_cache,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        /*
        $id = $contact_id ?: $id;

        $contact = $contact_repository->get($id, $pp->schema());
        $contact_type = $contact_repository->get_contact_type($contact['id_type_contact'], $pp->schema());

        if ($is_self)
        {
            $user_id = $su->id();
        }
        else if ($redirect_contacts)
        {
            $user_id = $contact['user_id'];
        }

        if ($user_id !== $contact['user_id'])
        {
            throw new BadRequestHttpException(
                'Contact ' . $id . ' does not belong to user ' . $user_id);
        }

        if (!$user_id)
        {
            throw new BadRequestHttpException('No user_id');
        }
        */

        $id = $contact_id ?: $id;

        $contact = $contact_repository->get($id, $pp->schema());
        $contact_type = $contact_repository->get_contact_type($contact['id_type_contact'], $pp->schema());

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
        $command->contact_type_id = $contact_type['name'];
        $command->value = $contact['value'];
        $command->comments = $contact['comments'];
        $command->access = $contact['access'];

        $form_options = [
            'contact_type_abbrev'   => $contact_type['abbrev'],
            'validation_groups'     => ['del'],
        ];

        $form = $this->createForm(ContactsDelType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $contact_repository->del($id, $pp->schema());

            $alert_service->success('Contact verwijderd.');

            if ($redirect_contacts)
            {
                return $this->redirectToRoute('contacts', $pp->ary());
            }

            return $this->redirectToRoute('users_show', [
                ...$pp->ary(),
                'id' => $user_id,
            ]);
        }

        if ($contact_type['abbrev'] === 'mail'
            && $user_cache->is_active_user($user_id, $pp->schema()))
        {
            $mail_count = $contact_repository->get_mail_count_for_user($user_id, $pp->schema());

            if ($mail_count === 1)
            {
                if ($pp->is_admin())
                {
                    $alert_service->warning(
                        'Waarschuwing: dit is het enige E-mail adres
                        van een actieve gebruiker');
                }
                else
                {
                    $alert_service->warning(
                        'Waarschuwing: dit is je enige E-mail adres.');
                }
            }
        }

        return $this->render('contacts/contacts_del.html.twig', [
            'form'              => $form->createView(),
            'contact'           => $contact,
            'contact_type'      => $contact_type,
            'is_self'           => $is_self,
            'user_id'           => $user_id,
            'redirect_contacts' => $redirect_contacts
        ]);
    }
}
