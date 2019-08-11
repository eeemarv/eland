<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Imagine\Imagick\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class users_image
{
    public function post(Request $request, app $app, string $status):Response
    {
        if ($app['s_id'] < 1)
        {
            return $app->json(['error' => 'Je hebt onvoldoende rechten voor deze actie.']);
        }

        return $this->post_admin($request, $app, $status, $app['s_id']);
    }

    public function post_admin(Request $request, app $app, string $status, int $id):Response
    {
        $image = $_FILES['image'] ?: null;

        if (!$image)
        {
            return $app->json(['error' => 'Afbeeldingsbestand ontbreekt.']);
        }

        $size = $image['size'];
        $tmp_name = $image['tmp_name'];
        $type = $image['type'];

        if ($size > 400 * 1024)
        {
            return $app->json(['error' => 'Het bestand is te groot.']);
        }

        if ($type != 'image/jpeg')
        {
            return $app->json(['error' => 'Ongeldig bestandstype.']);
        }

        //

        $exif = exif_read_data($tmp_name);

        $orientation = $exif['COMPUTED']['Orientation'] ?? false;

        $tmpfile = tempnam(sys_get_temp_dir(), 'img');

        $imagine = new Imagine();

        $image = $imagine->open($tmp_name);

        switch ($orientation)
        {
            case 3:
            case 4:
                $image->rotate(180);
                break;
            case 5:
            case 6:
                $image->rotate(-90);
                break;
            case 7:
            case 8:
                $image->rotate(90);
                break;
            default:
                break;
        }

        $image->thumbnail(new Box(400, 400), ImageInterface::THUMBNAIL_INSET);
        $image->save($tmpfile);

        //

        $filename = $app['tschema'] . '_u_' . $id . '_';
        $filename .= sha1($filename . microtime()) . '.jpg';

        $err = $app['s3']->img_upload($filename, $tmpfile);

        if ($err)
        {
            $app['monolog']->error('pict: ' .  $err . ' -- ' .
                $filename, ['schema' => $app['tschema']]);

            $response = ['error' => 'Afbeelding opladen mislukt.'];
        }
        else
        {
            $app['db']->update($app['tschema'] . '.users', [
                '"PictureFile"'	=> $filename
            ],['id' => $id]);

            $app['monolog']->info('User image ' . $filename .
                ' uploaded. User: ' . $id,
                ['schema' => $app['tschema']]);

            $app['user_cache']->clear($id, $app['tschema']);

            $response = ['success' => 1, 'filename' => $filename];
        }

        unlink($tmp_name);

        return $app->json($response);
    }
}
