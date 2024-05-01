<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cache\UserCache;
use App\Cache\UserInvalidateCache;
use App\Command\Users\UsersImageDelCommand;
use App\Form\Type\Users\UsersImageDelType;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersImageDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/image/del',
        name: 'users_image_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'is_self'       => false,
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/self/image/del',
        name: 'users_image_del_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'id'            => 0,
            'is_self'       => true,
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        bool $is_self,
        Db $db,
        AlertService $alert_service,
        UserCache $user_cache,
        UserInvalidateCache $user_invalidate_cache,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$is_self && $su->is_owner($id))
        {
            return $this->redirectToRoute('users_image_del_self', $pp->ary());
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        $user = $user_cache->get($id, $pp->schema());

        if (!$user)
        {
            throw new NotFoundHttpException('User with id ' . $id . ' not found.');
        }

        $image_file = $user['image_file'];

        if (!isset($image_file) || $image_file === '')
        {
            throw new NotFoundHttpException('No image file found for user with id ' . $id);
        }

        $command = new UsersImageDelCommand();

        $form = $this->createForm(UsersImageDelType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $db->update($pp->schema() . '.users',
                ['image_file' => null],
                ['id' => $id]);

            $user_invalidate_cache->user($id, $pp->schema());

            if ($is_self)
            {
                $alert_service->success('Je profielfoto/afbeelding is verwijderd');

                return $this->redirectToRoute('users_show_self', $pp->ary());
            }

            $alert_service->success('Profielfoto/afbeelding verwijderd.');

            return $this->redirectToRoute('users_show', [
                ...$pp->ary(),
                'id' => $id,
            ]);
        }

        return $this->render('users/users_image_del.html.twig', [
            'form'      => $form,
            'user'      => $user,
            'id'        => $id,
            'is_self'   => $is_self,
        ]);
    }
}
