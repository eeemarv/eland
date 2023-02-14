<?php declare(strict_types=1);

namespace App\Controller\ContactTypes;

use App\Repository\ContactRepository;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ContactTypesController extends AbstractController
{
    const PROTECTED = ['mail', 'gsm', 'tel', 'adr', 'web'];

    #[Route(
        '/{system}/{role_short}/contact-types',
        name: 'contact_types',
        methods: ['GET'],
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
        ContactRepository $contact_repository,
        PageParamsService $pp
    ):Response
    {
        $contact_types = $contact_repository->get_all_contact_types_with_count($pp->schema());

        foreach ($contact_types as &$ct)
        {
            if (in_array($ct['abbrev'], self::PROTECTED))
            {
                $ct['protected'] = true;
            }
        }

        return $this->render('contact_types/contact_types.html.twig', [
            'contact_types' => $contact_types,
        ]);
    }
}
