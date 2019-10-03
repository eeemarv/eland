<?php declare(strict_types=1);

namespace service;

use service\s3;
use Monolog\Logger as Monolog;
use Imagine\Imagick\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class image_upload
{
    const ALLOWED_MIME = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/gif'     => 'gif',
    ];

    const FILENAME_WITH_ID_TPL = '%schema%_%type%_%id%_%hash%.%ext%';
    const FILENAME_TPL = '%schema%_%type%_%hash%.%ext%';

	protected $monolog;
	protected $s3;

	public function __construct(
		Monolog $monolog,
		s3 $s3
	)
	{
		$this->monolog = $monolog;
		$this->s3 = $s3;
	}

	public function upload(
        UploadedFile $uploaded_file,
        string $type,
        int $id,
        int $width,
        int $height,
		string $schema
	):string
	{
        if (!$uploaded_file->isValid())
        {
            throw new BadRequestHttpException('Ongeldig bestand.');
        }

        $size = $uploaded_file->getSize();
        $mime = $uploaded_file->getMimeType();

        if ($size > 400 * 1024
            || $size > $uploaded_file->getMaxFilesize())
        {
            throw new HttpException(413, 'Het bestand is te groot.');
        }

        if (!in_array($mime, array_keys(self::ALLOWED_MIME)))
        {
            throw new UnsupportedMediaTypeHttpException('Ongeldig bestandstype.');
        }

        $tpl = $id < 1 ? self::FILENAME_TPL : self::FILENAME_WITH_ID_TPL;
        $ext = self::ALLOWED_MIME[$mime];

		$filename =  strtr($tpl, [
			'%schema%'		=> $schema,
			'%type%'		=> $type,
			'%id%'			=> $id,
            '%hash%'		=> sha1(random_bytes(16)),
            '%ext%'         => $ext,
		]);

        $tmp_upload_path = $uploaded_file->getRealPath();

        $exif = exif_read_data($tmp_upload_path);

        $orientation = $exif['COMPUTED']['Orientation'] ?? false;

        $tmp_after_resize_path = tempnam(sys_get_temp_dir(), 'img');

        $imagine = new Imagine();

        $image = $imagine->open($tmp_upload_path);

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

        $image->thumbnail(new Box($width, $height), ImageInterface::THUMBNAIL_INSET);
        $image->save($tmp_after_resize_path);

		$err = $this->s3->img_upload($filename, $tmp_after_resize_path);

        unlink($tmp_after_resize_path);
        unlink($tmp_upload_path);

        if ($err)
        {
            $this->monolog->error('image_upload: ' .  $err . ' -- ' .
				$filename, ['schema' => $schema]);

            throw new ServiceUnavailableHttpException('Afbeelding opladen mislukt.');
        }

        return $filename;
	}
}
