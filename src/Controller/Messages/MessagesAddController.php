<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Command\Messages\MessagesCommand;
use App\Form\Post\Messages\MessagesType;
use App\Render\AccountRender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Repository\MessageRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\SessionUserService;
use Psr\Log\LoggerInterface;

class MessagesAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        MessageRepository $message_repository,
        AlertService $alert_service,
        LinkRender $link_render,
        AccountRender $account_render,
        MenuService $menu_service,
        ConfigService $config_service,
        LoggerInterface $logger,
        S3Service $s3_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        $expires_at_required = $config_service->get_bool('messages.fields.expires_at.required', $pp->schema());
        $expires_at_days_default = $config_service->get_int('messages.fields.expires_at.days_default', $pp->schema());
        $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());
        $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
        $expires_at_switch_enabled = $config_service->get_bool('messages.fields.expires_at.switch_enabled', $pp->schema());
        $service_stuff_enabled = $config_service->get_bool('messages.fields.service_stuff.enabled', $pp->schema());
        $units_enabled = $config_service->get_bool('messages.fields.units.enabled', $pp->schema());

        $messages_command = new MessagesCommand();

        if ($pp->is_admin())
        {
            $messages_command->user_id = $su->id();
        }

        if (isset($expires_at_days_default))
        {
            $expires_at_unix = time() + ($expires_at_days_default * 86400);
            $expires_at =  gmdate('Y-m-d H:i:s', $expires_at_unix);
            $messages_command->expires_at = $expires_at;
        }

        /*
        'expires_at_switch_enabled'     => false,
        'expires_at_field_enabled'      => false,
        'category_id_field_enabled'     => false,
        'units_field_enabled'           => false,
        'offer_want_switch_enabled'     => false,
        'service_stuff_switch_enabled'  => false,
        */

        $form_options = [];
        $validation_groups = [];

        $form_options['offer_want_switch_enabled'] = true;
        $validation_groups[] = 'common';

        if ($pp->is_admin())
        {
            $validation_groups[] = 'user_id';
        }

        if ($service_stuff_enabled)
        {
            $form_options['service_stuff_switch_enabled'] = true;
            $validation_groups[] = 'service_stuff';
        }

        if ($category_enabled)
        {
            $form_options['category_id_field_enabled'] = true;
            $validation_groups[] = 'category_id';
        }

        if ($expires_at_enabled)
        {
            $validation_groups[] = 'expires_at';

            if ($expires_at_required)
            {
                $validation_groups[] = 'expires_at_required';
            }
            else
            {
                if ($expires_at_switch_enabled)
                {
                    $validation_groups[] = 'expires_at_switch';
                }
            }
        }

        if ($units_enabled)
        {
            $form_options['units_field_enabled'] = true;
            $validation_groups[] = 'units';
        }

        $switch_en = $expires_at_enabled
            && !$expires_at_required
            && $expires_at_switch_enabled;

        $form_options['validation_groups'] = $validation_groups;

        $form = $this->createForm(MessagesType::class,
            $messages_command, $form_options)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $messages_command = $form->getData();

            $user_id = $pp->is_admin() ? $messages_command->user_id : $su->id();

            $is_offer = $messages_command->offer_want === 'offer';
            $subject = $messages_command->subject;

            $message = [
                'is_offer'      => $is_offer ? 't' : 'f',
                'is_want'       => $is_offer ? 'f' : 't',
                'subject'       => $subject,
                'content'       => $messages_command->content,
                'image_files'   => $messages_command->image_files,
                'access'        => $messages_command->access,
                'user_id'       => $user_id,
                'created_by'    => $su->id(),
            ];

            if ($category_enabled)
            {
                $message['category_id'] = $messages_command->category_id;
            }

            if ($expires_at_enabled)
            {
                if (!$expires_at_required
                    && $expires_at_switch_enabled)
                {
                    if ($message['expires_at_switch'] === 'temporal')
                    {
                        $message['expires_at'] = $messages_command->expires_at;
                    }
                }
                else
                {
                    $message['expires_at'] = $messages_command->expires_at;
                }
            }

            if ($units_enabled)
            {
                $message['amount'] = $messages_command->amount;
                $message['units'] = $messages_command->units;
            }

            $id = $message_repository->insert($message, $pp->schema());

            $images = array_values(json_decode($messages_command->image_files, true) ?? []);
            $new_image_files = [];
            $update_image_files = false;

            foreach ($images as $img)
            {
                [$img_schema, $img_type, $img_msg_id, $img_file_name] = explode('_', $img);
                [$img_id, $img_ext] = explode('.', $img_file_name);

                $img_msg_id = (int) $img_msg_id;

                if ($img_schema !== $pp->schema())
                {
                    $logger->debug('Schema does not fit image (not inserted): ' . $img,
                        ['schema' => $pp->schema()]);
                    $update_image_files = true;
                    continue;
                }

                if ($img_type !== 'm')
                {
                    $logger->debug('Type does not fit image message (not inserted): ' . $img,
                        ['schema' => $pp->schema()]);

                    $update_image_files = true;
                    continue;
                }

                if ($img_msg_id !== $id)
                {
                    $new_filename = $pp->schema() . '_m_' . $id . '_';
                    $new_filename .= sha1(random_bytes(16)) . '.' . $img_ext;

                    $err = $s3_service->copy($img, $new_filename);

                    if (isset($err))
                    {
                        $logger->error('message image renaming and storing in db ' .
                            $img .  ' not succeeded. ' . $err,
                            ['schema' => $pp->schema()]);
                    }
                    else
                    {
                        $logger->info('renamed ' . $img . ' to ' .
                            $new_filename, ['schema' => $pp->schema()]);

                        $new_image_files[] = $new_filename;
                    }

                    $update_image_files = true;
                    continue;
                }

                $new_image_files[] = $img;
            }

            if ($update_image_files)
            {
                $message_update = [
                    'image_files'   => json_encode(array_values($new_image_files)),
                ];

                $message_repository->update($message_update, $id, $pp->schema());
            }

            $alert_trans_key = 'messages_add.success.';
            $alert_trans_key .= $is_offer ? 'offer.' : 'want.';
            $alert_trans_key .= $su->is_owner($user_id) ? 'personal' : 'admin';

            $alert_service->success($alert_trans_key, [
                '%message_subject%' => $subject,
                '%user%'            => $account_render->get_str($user_id, $pp->schema()),
            ]);

            $link_render->redirect('messages_show', $pp->ary(),
                ['id' => $id]);
        }

        $menu_service->set('messages');

        return $this->render('messages/messages_add.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
