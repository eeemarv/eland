<?php declare(strict_types=1);

namespace App\Controller\ContactTypes;

use App\Form\Post\DelType;
use App\Repository\ContactRepository;
use App\Service\AlertService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Routing\Annotation\Route;

class ContactTypesDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/contact-types/{id}/del',
        name: 'contact_types_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'contact_types',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        ContactRepository $contact_repository,
        AlertService $alert_service,
        PageParamsService $pp
    ):Response
    {
        $contact_type = $contact_repository->get_contact_type($id, $pp->schema());

        if (in_array($contact_type['abbrev'], ContactTypesController::PROTECTED))
        {
            throw new BadRequestException('Protected contact type.');
        }

        $count_contacts = $contact_repository->get_count_for_contact_type($id, $pp->schema());

        if ($count_contacts > 0)
        {
            throw new BadRequestException('A contact type with contacts can not be deleted.');
        }

        $form = $this->createForm(DelType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $contact_repository->del_contact_type($id, $pp->schema());
            $alert_service->success('Contact type "' . $contact_type['name'] . '" verwijderd.');
            return $this->redirectToRoute('contact_types', $pp->ary());
        }

        return $this->render('contact_types/contact_types_del.html.twig', [
            'form'          => $form->createView(),
            'contact_type'  => $contact_type,
        ]);
    }
}
