<?php declare(strict_types=1);

namespace App\Controller\Contacts;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Command\Contacts\ContactsCommand;
use App\Form\Post\Contacts\ContactsType;
use App\Queue\GeocodeQueue;
use App\Service\AlertService;
use App\Repository\ContactRepository;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\Routing\Annotation\Route;

class ContactsAddController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/contacts/add',
        name: 'contacts_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'redirect_contacts'     => true,
            'user_id'               => 0,
            'is_self'               => false,
            'module'                => 'users',
            'sub_module'            => 'contacts',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/{user_id}/contacts/add',
        name: 'users_contacts_add',
        methods: ['GET', 'POST'],
        requirements: [
            'user_id'       => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'redirect_contacts'     => false,
            'is_self'               => false,
            'module'                => 'users',
            'sub_module'            => 'contacts',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/contacts/add',
        name: 'users_contacts_add_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
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
        bool $is_self,
        bool $redirect_contacts,
        ContactRepository $contact_repository,
        AlertService $alert_service,
        GeocodeQueue $geocode_queue,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if ($is_self)
        {
            $user_id = $su->id();
        }

        $command = new ContactsCommand();

        if ($user_id)
        {
            $command->user_id = $user_id;
        }

        $form = $this->createForm(ContactsType::class,
            $command, ['user_id_enabled' => $redirect_contacts]);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $created_by = $su->is_master() ? null : $su->id();
            $contact_repository->insert($command, $created_by, $pp->schema());

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

            $alert_service->success('Contact opgeslagen.');

            if ($redirect_contacts)
            {
                return $this->redirectToRoute('contacts', $pp->ary());
            }

            return $this->redirectToRoute('users_show', array_merge($pp->ary(),
                ['id' => $user_id]));
        }

        return $this->render('contacts/contacts_add.html.twig', [
            'form'              => $form->createView(),
            'is_self'           => $is_self,
            'user_id'           => $user_id,
            'redirect_contacts' => $redirect_contacts,
        ]);
    }
}
