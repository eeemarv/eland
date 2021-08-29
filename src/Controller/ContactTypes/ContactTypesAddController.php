<?php declare(strict_types=1);

namespace App\Controller\ContactTypes;

use App\Command\ContactTypes\ContactTypesCommand;
use App\Form\Type\ContactTypes\ContactTypesType;
use App\Repository\ContactRepository;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactTypesAddController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/contact-types/add',
        name: 'contact_types_add',
        methods: ['GET', 'POST'],
        requirements: [
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
        ContactRepository $contact_repository,
        AlertService $alert_service,
        PageParamsService $pp
    ):Response
    {
        $command = new ContactTypesCommand();
        $form = $this->createForm(ContactTypesType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $contact_repository->insert_contact_type($command, $pp->schema());

            $alert_service->success('Contact type toegevoegd.');
            return $this->redirectToRoute('contact_types', $pp->ary());
        }

        return $this->render('contact_types/contact_types_add.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
