<?php declare(strict_types=1);

namespace App\Controller\ContactTypes;

use App\Command\ContactTypes\ContactTypesCommand;
use App\Form\Post\ContactTypes\ContactTypesType;
use App\Repository\ContactRepository;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Routing\Annotation\Route;

class ContactTypesEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/contact-types/{id}/edit',
        name: 'contact_types_edit',
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

        $command = new ContactTypesCommand();
        $command->id = $id;
        $command->name = $contact_type['name'];
        $command->abbrev = $contact_type['abbrev'];
        $form = $this->createForm(ContactTypesType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $contact_repository->update_contact_type($command, $pp->schema());

            $alert_service->success('Contact type aangepast.');
            return $this->redirectToRoute('contact_types', $pp->ary());
        }

        return $this->render('contact_types/contact_types_edit.html.twig', [
            'form'   => $form->createView(),
        ]);
    }
}
