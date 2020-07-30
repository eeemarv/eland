<?php declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Imagine\Imagick\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ImageUploadService
{
    const MAX_SIZE = 400 * 1024;
    const ALLOWED_MIME = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/gif'     => 'gif',
        'image/svg+xml' => 'svg',
    ];

    const FILENAME_WITH_ID_TPL = '%schema%_%type%_%id%_%hash%.%ext%';
    const FILENAME_TPL = '%schema%_%type%_%hash%.%ext%';
    const NODE_PATH = '/usr/bin/node';
    const SVGO_PATH = __DIR__ . '/../../node_modules/svgo/bin/svgo';
    const SVGO_EN = [
        'removeDimensions',
        'cleanupListOfValues',
        'removeRasterImages',
        'sortAttrs',
        'removeOffCanvasPaths',
        'removeScriptElement',
        'reusePaths',
    ];
    const SVGO_DIS = [
        'removeViewBox',
    ];
    const SVGO_PRECISION = 3;

	protected LoggerInterface $logger;
    protected S3Service $s3_service;

	public function __construct(
		LoggerInterface $logger,
		S3Service $s3_service
	)
	{
		$this->logger = $logger;
		$this->s3_service = $s3_service;
	}

	public function upload(
        UploadedFile $uploaded_file,
        string $type,
        int $id,
        int $width,
        int $height,
        bool $crop_to_square,
		string $schema
	):string
	{
        if (!$uploaded_file->isValid())
        {
            throw new BadRequestHttpException('Ongeldig bestand.');
        }

        $size = $uploaded_file->getSize();
        $mime = $uploaded_file->getMimeType();

        if ($size > self::MAX_SIZE
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

        if ($ext === 'svg')
        {
            $this->upload_svg($tmp_upload_path, $filename, $crop_to_square, $schema);
            return $filename;
        }

        $this->upload_bitmap($tmp_upload_path, $filename, $ext, $width, $height, $crop_to_square, $schema);
        return $filename;
    }

    protected function upload_bitmap(
        string $tmp_upload_path,
        string $filename,
        string $ext,
        int $width,
        int $height,
        bool $crop_to_square,
        string $schema
    ):void
    {
        if ($ext === 'jpg')
        {
            $exif = exif_read_data($tmp_upload_path);
            $orientation = $exif['COMPUTED']['Orientation'] ?? 1;
        }
        else
        {
            $orientation = 1;
        }

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

        $max_box = new Box($width, $height);

        if ($crop_to_square)
        {
            $box = $image->getSize();

            if ($box->getHeight() < $max_box->getHeight())
            {

            }

        }
        else
        {
            $thumbnail = $image->thumbnail($max_box, ImageInterface::THUMBNAIL_INSET);
        }

        $thumbnail->save($tmp_after_resize_path);

		$err = $this->s3_service->img_upload($filename, $tmp_after_resize_path);

        unlink($tmp_upload_path);
        unlink($tmp_after_resize_path);

        if ($err)
        {
            $this->logger->error('image_upload: ' .  $err . ' -- ' .
				$filename, ['schema' => $schema]);

            throw new HttpException(800, 'Afbeelding opladen mislukt.');
        }
    }

    protected function upload_svg(
        string $tmp_upload_path,
        string $filename,
        bool $crop_to_square,
        string $schema
    ):void
    {
        $tmp_after_optimize_path = tempnam(sys_get_temp_dir(), 'img');
        $enabled = implode(',', self::SVGO_EN);
        $disabled = implode(',', self::SVGO_DIS);
        $process_args = [
            self::NODE_PATH,
            self::SVGO_PATH,
            '--enable=' . $enabled,
            '--disable=' . $disabled,
            '-p',
            self::SVGO_PRECISION,
            '-i',
            $tmp_upload_path,
            '-o',
            $tmp_after_optimize_path,
        ];
        $process = new Process($process_args);
        $process->run();
        if (!$process->isSuccessful())
        {
            throw new ProcessFailedException($process);
        }

        $this->logger->debug('svgo compress (' . $filename . ') ' . $process->getOutput(), ['schema' => $schema]);

        if ($crop_to_square)
        {
            $do_crop = false;
            $doc = new \DOMDocument();
            $doc->load($tmp_after_optimize_path);
            $svg = $doc->documentElement;
            $viewbox = $svg->getAttribute('viewBox');
            $viewbox = strtr($viewbox, [
                '   '   => ' ',
                '  '    => ' ',
                ','     => ' ',
            ]);
            [$x, $y, $w, $h] = explode(' ', $viewbox);
            if ($w < $h)
            {
                $do_crop = true;
                $x -= ($h - $w) / 2;
                $x = round($x, 3);
                $w = $h;
            }
            else if ($h < $w)
            {
                $do_crop = true;
                $y -= ($w - $h) / 2;
                $y = round($y, 3);
                $h = $w;
            }

            if ($do_crop)
            {
                $viewbox = $x . ' ' . $y . ' ' . $w . ' ' . $h;
                $svg->setAttribute('viewBox', $viewbox);
                $doc->save($tmp_after_optimize_path);

                $this->logger->debug('svg pad to square (' . $filename  . ')', ['schema' => $schema]);
            }
        }

        $err = $this->s3_service->img_upload($filename, $tmp_after_optimize_path);

        unlink($tmp_after_optimize_path);
        unlink($tmp_upload_path);

        if ($err)
        {
            $this->logger->error('image_upload: ' .  $err . ' -- ' .
				$filename, ['schema' => $schema]);

            throw new ServiceUnavailableHttpException('Afbeelding opladen mislukt.');
        }
    }
}
