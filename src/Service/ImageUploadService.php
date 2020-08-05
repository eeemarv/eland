<?php declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Imagine\Imagick\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Intervention\Image\ImageManagerStatic;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
    const NODE_PATH = 'node';
    const SVGO_PATH = __DIR__ . '/../../node_modules/svgo/bin/svgo';
    const SVGO_DIS = [
        'removeViewBox',
    ];
    const SVGO_EN = [
        'cleanupListOfValues',
        'removeRasterImages',
        'sortAttrs',
        'sortDefsChildren',
        'removeOffCanvasPaths',
        'removeScriptElement',
        'reusePaths',
        'removeDimensions',
    ];
    const SVGO_PRECISION = 3;
    const SVG_CROP_ALLOWED_PADDING_PERCENTAGE = 10;
    const UNIT_CONV = [
        'px'    => 1,
        'in'    => 96,
        'cm'    => 37.795,
        'mm'    => 3.7795,
        'pt'    => 1.3333,
        'pc'    => 16,
        'em'    => 16,
        'ex'    => 10,
        'rem'   => 16,
        'ch'    => 10,
    ];

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
	):array
	{
        if (!$uploaded_file->isValid())
        {
            return [
                'error' => 'Ongeldig bestand',
                'code'  => 400,
            ];
        }

        $size = $uploaded_file->getSize();
        $mime = $uploaded_file->getMimeType();

        if ($size > self::MAX_SIZE
            || $size > $uploaded_file->getMaxFilesize())
        {
            return [
                'error' => 'Het bestand is te groot.',
                'code'  => 413,
            ];
        }

        if (!in_array($mime, array_keys(self::ALLOWED_MIME)))
        {
            return [
                'error'     => 'Ongeldig bestandstype.',
                'code'      => 415,
            ];
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
            return $this->upload_svg($tmp_upload_path, $filename, $crop_to_square, $schema);
        }

        return $this->upload_bitmap($tmp_upload_path, $filename, $ext, $width, $height, $crop_to_square, $schema);
    }

    protected function upload_bitmap(
        string $tmp_upload_path,
        string $filename,
        string $ext,
        int $width,
        int $height,
        bool $crop_to_square,
        string $schema
    ):array
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

        ImageManagerStatic::configure(['driver' => 'imagick']);
        $image = ImageManagerStatic::make($tmp_upload_path);

        switch ($orientation)
        {
            case 2:
                $image->flip();
                break;
            case 4:
                $image->flip();
            case 3:
                $image->rotate(180);
                break;
            case 5:
                $image->flip();
            case 6:
                $image->rotate(270);
                break;
            case 7:
                $image->flip();
            case 8:
                $image->rotate(90);
                break;
            default:
                break;
        }

        $h = $image->height();
        $w = $image->width();
        $rh = $h / $height;
        $rw = $w / $width;

        if ($rh > 1 || $rw > 1)
        {
            if ($rh > $rw)
            {
                $h = $height;
                $w = round($w / $rh);
            }
            else
            {
                $w = $width;
                $h = round($h / $rw);
            }

            $image->resize($w, $h);
        }

        if ($crop_to_square)
        {
            if ($w > $h)
            {
                $x = round(($w - $h) / 2);
                $image->crop($h, $h, $x, 0);
            }
            else if ($h > $w)
            {
                $y = round(($h - $w) / 2);
                $image->crop($w, $w, 0, $y);
            }
        }

        $image->save($tmp_upload_path);

		$err = $this->s3_service->img_upload($filename, $tmp_upload_path);

        unlink($tmp_upload_path);

        if ($err)
        {
            $this->logger->error('image_upload: ' .  $err . ' -- ' .
				$filename, ['schema' => $schema]);

            return [
                'error' => 'Afbeelding opladen mislukt.',
                'code'  => 400,
            ];
        }

        return [
            'filename'  => $filename,
            'code'      => 200,
        ];
    }

    protected function upload_svg(
        string $tmp_upload_path,
        string $filename,
        bool $crop_to_square,
        string $schema
    ):array
    {
        $enabled = implode(',', self::SVGO_EN);
        $disabled = implode(',', self::SVGO_DIS);
        $process_args = [
            self::NODE_PATH,
            self::SVGO_PATH,
            '--disable=' . $disabled,
            '--enable=' . $enabled,
            '-p',
            self::SVGO_PRECISION,
            '-i',
            $tmp_upload_path,
            '-o',
            $tmp_upload_path,
        ];
        $process = new Process($process_args);
        $process->run();
        $this->logger->debug('svgo compress (' . $filename . ') ' . $process->getOutput(), ['schema' => $schema]);
        if (!$process->isSuccessful())
        {
            $this->logger->debug('svgo process fail: ' . $process->getErrorOutput(), ['schema' => $schema]);
            return [
                'error'     => 'Proces fout.',
                'code'      => 500,
            ];
        }

        $rewrite = false;
        $crop = false;
        $doc = new \DOMDocument();
        $doc->load($tmp_upload_path);
        $svg = $doc->documentElement;
        $viewbox = $svg->getAttribute('viewBox');
        if ($viewbox === '')
        {
            $rewrite = true;
            $height = $svg->getAttribute('height');
            $width = $svg->getAttribute('width');
            $svg->removeAttribute('height');
            $svg->removeAttribute('width');
            $h = filter_var($height, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $w = filter_var($width, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            foreach(self::UNIT_CONV as $unit => $conv)
            {
                if (stripos($height, $unit) !== false)
                {
                    $h = $h * $conv;
                }
                if (stripos($width, $unit) !== false)
                {
                    $w = $w * $conv;
                }
            }
            $x = 0;
            $y = 0;
        }
        else
        {
            $viewbox = strtr($viewbox, [
                '   '   => ' ',
                '  '    => ' ',
                ','     => ' ',
            ]);
            [$x, $y, $w, $h] = explode(' ', $viewbox);
        }

        if ($crop_to_square)
        {
            $pad_ratio = (self::SVG_CROP_ALLOWED_PADDING_PERCENTAGE + 100) / 100;
            if ($w < $h)
            {
                $rewrite = true;
                $crop = true;
                $p_w = $pad_ratio * $w;
                if ($p_w <= $h)
                {
                    $y += ($h - $p_w) / 2;
                    $x -= ($p_w - $w) / 2;
                    $w = $p_w;
                    $h = $p_w;
                }
                else
                {
                    $x -= ($h - $w) / 2;
                    $w = $h;
                }
            }
            else if ($h < $w)
            {
                $rewrite = true;
                $crop = true;
                $p_h = $pad_ratio * $h;
                if ($p_h <= $w)
                {
                    $x += ($w - $p_h) / 2;
                    $y -= ($p_h - $h) / 2;
                    $w = $p_h;
                    $h = $p_h;
                }
                else
                {
                    $y -= ($w - $h) / 2;
                    $h = $w;
                }
            }

            if ($rewrite)
            {
                $x = round($x, 3);
                $y = round($y, 3);
                $w = round($w, 3);
                $h = round($h, 3);

                $viewbox = $x . ' ' . $y . ' ' . $w . ' ' . $h;
                $svg->setAttribute('viewBox', $viewbox);
                $doc->save($tmp_upload_path);

                if ($crop)
                {
                    $this->logger->debug('svg crop to square (' . $filename  . ')', ['schema' => $schema]);
                    $process = new Process($process_args);
                    $process->run();
                    $this->logger->debug('svgo compress after crop (' . $filename . ') ' . $process->getOutput(), ['schema' => $schema]);
                    if (!$process->isSuccessful())
                    {
                        $this->logger->debug('svgo process fail (2): ' . $process->getErrorOutput(), ['schema' => $schema]);
                        return [
                            'error'     => 'Proces fout (2).',
                            'code'      => 500,
                        ];
                    }
                }
            }
        }

        $err = $this->s3_service->img_upload($filename, $tmp_upload_path);

        unlink($tmp_upload_path);

        if ($err)
        {
            $this->logger->error('image_upload: ' .  $err . ' -- ' .
				$filename, ['schema' => $schema]);

            return [
                'error' => 'Afbeelding opladen mislukt.',
                'code'  => 400,
            ];
        }

        return [
            'filename'  => $filename,
            'code'      => 200,
        ];
    }
}
