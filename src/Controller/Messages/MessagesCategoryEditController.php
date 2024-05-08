<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Cache\ConfigCache;
use App\Command\Messages\MessagesCategoryCommand;
use App\Form\Type\Messages\MessagesCategoryEditType;
use App\Repository\CategoryRepository;
use App\Repository\MessageRepository;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MessagesCategoryEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}/category/edit',
        name: 'messages_category_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        AlertService $alert_service,
        CategoryRepository $category_repository,
        MessageRepository $message_repository,
        ConfigCache $config_cache,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_cache->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages module not enabled in configuration');
        }

        if (!$config_cache->get_bool('messages.fields.category.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Category submodule not enabled in configuration');
        }

        $message = $message_repository->get($id, $pp->schema());

        $user_id = $message['user_id'];

        if (!$pp->is_admin() && !$su->is_owner($user_id))
        {
            throw new AccessDeniedHttpException('You are not allowed to modify this message');
        }

        $command = new MessagesCategoryCommand();

        $command->category_id = $message['category_id'];
        $category_id = $message['category_id'];

        $form = $this->createForm(MessagesCategoryEditType::class, $command);

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($category_id === $command->category_id)
            {
                $alert_service->warning('Categorie niet gewijzigd');
            }
            else
            {
                $update_ary = [
                    'category_id'   => $command->category_id,
                ];

                $old_cat = $category_repository->get($category_id, $pp->schema());
                $new_cat = $category_repository->get($command->category_id, $pp->schema());

                $old_name = $old_cat['parent_name'] ?? '';
                $old_name .= isset($old_cat['parent_name']) ? ' > ' : '';
                $old_name .= $old_cat['name'];

                $new_name = $new_cat['parent_name'] ?? '';
                $new_name .= isset($new_cat['parent_name']) ? ' > ' : '';
                $new_name .= $new_cat['name'];

                $message_repository->update($update_ary, $id, $pp->schema());

                $alert_service->success('Categorie gewijzigd van "' . $old_name . '" naar "' . $new_name . '"');
            }

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id'    => $id,
            ]);
        }

        return $this->render('messages/messages_category_edit.html.twig', [
            'form'      => $form->createView(),
            'message'   => $message,
            'user_id'   => $user_id,
        ]);
    }
}
