<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Imagine\Imagick\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class users_image_upload
{
    public function upload_self(Request $request, app $app):Response
    {
        if ($app['s_id'] < 1)
        {
            return $app->json(['error' => 'Je hebt onvoldoende rechten voor deze actie.']);
        }

        return $this->upload_admin($request, $app, $app['s_id']);
    }

    public function upload_admin(Request $request, app $app, int $id):Response
    {
        $uploaded_file = $request->files->get('image');

        if (!$uploaded_file)
        {
            return $app->json(['error' => 'Afbeeldingsbestand ontbreekt.']);
        }

        if (!$uploaded_file->isValid())
        {
            return $app->json(['error' => 'Fout bij het opladen.']);
        }

        $size = $uploaded_file->getSize();

        if ($size > 400 * 1024
            || $size > $uploaded_file->getMaxFilesize())
        {
            return $app->json(['error' => 'Het bestand is te groot.']);
        }

        if ($uploaded_file->getMimeType() !== 'image/jpeg')
        {
            return $app->json(['error' => 'Ongeldig bestandstype.']);
        }

        //

        $image_tmp_path = $uploaded_file->getRealPath();

        $exif = exif_read_data($image_tmp_path);

        $orientation = $exif['COMPUTED']['Orientation'] ?? false;

        $tmpfile = tempnam(sys_get_temp_dir(), 'img');

        $imagine = new Imagine();

        $image = $imagine->open($image_tmp_path);

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

//        unlink($tmp_name);
        unlink($image_tmp_path);

        return $app->json($response);
    }
}
