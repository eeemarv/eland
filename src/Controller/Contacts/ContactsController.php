<?php declare(strict_types=1);

namespace App\Controller\Contacts;

use App\Command\Contacts\ContactsBulkAccessCommand;
use App\Command\Contacts\ContactsFilterCommand;
use App\Form\Type\Contacts\ContactsBulkAccessType;
use App\Form\Type\Contacts\ContactsFilterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ContactRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class ContactsController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/contacts',
    name: 'contacts',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'users',
      'sub_module'    => 'contacts',
    ],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    ContactRepository $contact_repository,
    PageParamsService $pp,
  ):Response
  {
    $filter_command = new ContactsFilterCommand();

    $uid = $request->query->get('uid');

    if (isset($uid))
    {
      $filter_command->user = (int) $uid;
    }

    $filter_form_options = ['validation_groups' => ['filter']];

    $filter_form = $this->createForm(ContactsFilterType::class, $filter_command, $filter_form_options);
    $filter_form->handleRequest($request);

    // $f_params = $request->query->all('f');
    // $filter_form_error = isset($f_params['user']) && !isset($filter_command->user);

    $filter_form_error = $filter_form->isSubmitted() && !$filter_form->isValid();

    $intersystem_enabled = $config_service->get_bool(
      config_id: 'intersystem.enabled',
      schema: $pp->schema_o(),
    );

    $new_user_treshold = $config_service->get_new_user_treshold(
      schema: $pp->schema_o(),
    );

    $bulk_access_command = new ContactsBulkAccessCommand();
    $bulk_access_form = $this->createForm(
      type: ContactsBulkAccessType::class,
      data: $bulk_access_command,
    );
    $bulk_access_form->handleRequest($request);

    if ($bulk_access_form->isSubmitted()
      && $bulk_access_form->isValid()
    )
    {
      $selected = $bulk_access_command->selected;
      $access = $bulk_access_command->access;
      $select_ary = explode(',', $selected);
      $s_contact_ids = [];

      foreach ($select_ary as $sel_id)
      {
        $s_contact_ids[] = (int) trim($sel_id);
      }

      $s_contacts = $contact_repository->get_selected(
        selected_ids: $s_contact_ids,
        schema: $pp->schema_o(),
      );

      $contact_repository->set_bulk_access(
        access: $access,
        selected_ids: $s_contact_ids,
        schema: $pp->schema_o(),
      );

      $change_count = 0;
      $no_change_count = 0;

      foreach($s_contact_ids as $cid)
      {
        if ($s_contacts[$cid]['access'] === $access)
        {
          $no_change_count++;
        }
        else
        {
          $change_count++;
        }
      }

      if ($change_count)
      {
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'contacts.bulk.access.flash.success',
            'params'  => [
              'count' => $change_count,
            ],
          ],
        );
      }

      if ($no_change_count)
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'contacts.bulk.access.flash.warning',
            'params' => [
              'count' => $no_change_count,
            ]
          ],
        );
      }

      return $this->redirectToRoute(
        route: 'contacts',
        parameters: $pp->ary(),
      );
    }

    $pag = $request->query->all('p');
    $sort = $request->query->all('s');

    $pag_start = (int) ($pag['start'] ?? 0);
    $pag_limit = (int) ($pag['limit'] ?? 25);
    $sort_order_by = $sort['order_by'] ?? 'id';
    $sort_asc = isset($sort['asc']) && $sort['asc'] ? true : false;

    $pag_params = [
      's'	=> [
        'order_by'	=> $sort_order_by,
        'asc'		=> $sort_asc,
      ],
      'p'	=> [
        'start'		=> $pag_start,
        'limit'		=> $pag_limit,
      ],
    ];

    $ret = $contact_repository->get_filtered(
      value: $filter_command->q ?? null,
      user_id: $filter_command->user ?? null,
      contact_type_id: $filter_command->type ?? null,
      access_ary: $filter_command->access ?? null,
      user_status: $filter_command->ustatus ?? null, 
      start: $pag_start,
      limit: $pag_limit,
      order_by: $sort_order_by,
      asc: $sort_asc,
      intersystem_enabled: $intersystem_enabled,
      new_user_treshold: $new_user_treshold,
      schema: $pp->schema_o(),
    );

    $count_ary = $ret['count_ary'];
    $contacts = $ret['contacts'];

    $filtered = !isset($uid) && (
      isset($filter_command->q)
      || isset($filter_command->type)
      || isset($filter_command->user)
      || isset($filter_command->ustatus)
      || (isset($filter_command->access) && $filter_command->access)
    );

    $filter_collapse = !($filtered || $filter_form_error);

    return $this->render('contacts/contacts_list.html.twig', [
      'pag_params'        => $pag_params,
      'contacts'          => $contacts,
      'row_count'         => $count_ary['row_count'],
      'filtered'          => $filtered,
      'filter_collapse'   => $filter_collapse,
      'filter_form'       => $filter_form->createView(),
      'count_ary'         => $count_ary,
      'bulk_actions_enabled'  => true,
      'bulk_access_form'  => $bulk_access_form,
    ]);
  }
}
