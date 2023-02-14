<?php declare(strict_types=1);

namespace App\Service;

use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\CachingStream;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class S3Service
{
	protected S3Client $s3_client;

	const IMG_TYPES = [
		'jpg'	=> 'image/jpeg',
		'jpeg'	=> 'image/jpeg',
		'png'	=> 'image/png',
		'gif'	=> 'image/gif',
		'svg'	=> 'image/svg+xml',
	];

	const DOC_TYPES = [
		'docx'		=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'docm'		=> 'application/vnd.ms-word.document.macroEnabled.12',
		'dotx'		=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		'dotm'		=> 'application/vnd.ms-word.template.macroEnabled.12',
		'xlsx'		=> 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'xlsm'		=> 'application/vnd.ms-excel.sheet.macroEnabled.12',
		'xltx'		=> 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
		'xltm'		=> 'application/vnd.ms-excel.template.macroEnabled.12',
		'xlsb'		=> 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
		'xlam'		=> 'application/vnd.ms-excel.addin.macroEnabled.12',
		'pptx'		=> 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'pptm'		=> 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
		'ppsx'		=> 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
		'ppsm'		=> 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',
		'potx'		=> 'application/vnd.openxmlformats-officedocument.presentationml.template',
		'potm'		=> 'application/vnd.ms-powerpoint.template.macroEnabled.12',
		'ppam'		=> 'application/vnd.ms-powerpoint.addin.macroEnabled.12',
		'sldx'		=> 'application/vnd.openxmlformats-officedocument.presentationml.slide',
		'sldm'		=> 'application/vnd.ms-powerpoint.slide.macroEnabled.12',
		'one'		=> 'application/msonenote',
		'onetoc2'	=> 'application/msonenote',
		'onetmp'	=> 'application/msonenote',
		'onepkg'	=> 'application/msonenote',
		'thmx'		=> 'application/vnd.ms-officetheme',
		'doc'		=> 'application/msword',
		'dot'		=> 'application/msword',
		'xls'		=> 'application/vnd.ms-excel',
		'xlt'		=> 'application/vnd.ms-excel',
		'xla'		=> 'application/vnd.ms-excel',
		'ppt' 		=> 'application/vnd.ms-powerpoint',
		'pot'		=> 'application/vnd.ms-powerpoint',
		'pps'		=> 'application/vnd.ms-powerpoint',
		'ppa'		=> 'application/vnd.ms-powerpoint',
		'css'		=> 'text/css',
		'html'		=> 'text/html',
		'md'		=> 'text/markdown',
		'pdf'		=> 'application/pdf',
	];

	public function __construct(
		#[Autowire('%env(AWS_S3_BUCKET)%')]
		protected string $env_aws_s3_bucket,
		#[Autowire('%env(AWS_S3_REGION)%')]
		protected string $env_aws_s3_region
	)
	{
		$this->s3_client = S3Client::factory([
			'signature'	=> 'v4',
			'region'	=> $this->env_aws_s3_region,
			'version'	=> '2006-03-01',
		]);
	}

	public function exists(string $filename):bool
	{
		return $this->s3_client->doesObjectExist($this->env_aws_s3_bucket, $filename);
	}

	public function img_upload(
		string $filename,
		string $tmpfile
	):string
	{
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		$content_type = self::IMG_TYPES[$ext] ?? false;

		if (!$content_type)
		{
			return 'Geen geldig bestandstype.';
		}

		try {
			$this->s3_client->putObject([
				'Bucket' 		=> $this->env_aws_s3_bucket,
				'Key'    		=> $filename,
				'Body'          => new CachingStream(
					new Stream(fopen($tmpfile, 'rb'))
				),
				'ACL'           => 'public-read',
				'CacheControl'	=> 'public, max-age=31536000',
				'ContentType'	=> $content_type,
			]);
		}
		catch(\Exception $e)
		{
			return 'Opladen mislukt: ' . $e->getMessage();
		}

		return '';
	}

	public function doc_upload(
		string $filename,
		string $tmpfile
	):string
	{
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		$content_type = self::DOC_TYPES[$ext] ?? false;

		if (!$content_type)
		{
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$content_type = finfo_file($finfo, $tmpfile);
			finfo_close($finfo);
		}

		if (!$content_type)
		{
			return 'Geen geldig bestandstype.';
		}

		try
		{
			$this->s3_client->putObject([
				'Bucket' 		=> $this->env_aws_s3_bucket,
				'Key'    		=> $filename,
				'Body'          => new CachingStream(
					new Stream(fopen($tmpfile, 'rb'))
				),
				'ACL'           => 'public-read',
				'CacheControl'	=> 'public, max-age=31536000',
				'ContentType'	=> $content_type,
			]);
		}
		catch(\Exception $e)
		{
			return 'Opladen mislukt: ' . $e->getMessage();
		}

		return '';
	}

	public function copy(string $source, string $destination)
	{
		try
		{
			$result = $this->s3_client->getObject([
				'Bucket' => $this->env_aws_s3_bucket,
				'Key'    => $source,
			]);

			$this->s3_client->copyObject([
				'Bucket'		=> $this->env_aws_s3_bucket,
				'CopySource'	=> $this->env_aws_s3_bucket . '/' . $source,
				'Key'			=> $destination,
				'ACL'			=> 'public-read',
				'CacheControl'	=> 'public, max-age=31536000',
				'ContentType'	=> $result['ContentType'],
			]);
		}
		catch (\Exception $e)
		{
			error_log('s3 copy : ' . $e->getMessage());
		}

		return;
	}

	public function del(string $filename):void
	{
		try
		{
			$this->s3_client->deleteObject([
				'Bucket'	=> $this->env_aws_s3_bucket,
				'Key'		=> $filename,
			]);
		}
		catch (\Exception $e)
		{
			error_log('s3 del: ' . $e->getMessage());
		}
	}

	public function list(int $num = 10, string $marker = '0')
	{
		$params = [
			'Bucket'	=> $this->env_aws_s3_bucket,
			'Marker'	=> $marker,
			'MaxKeys'	=> $num,
		];

		try
		{
			$objects = $this->s3_client->getIterator('ListObjects', $params);

			return $objects;
		}
		catch (\Exception $e)
		{
			error_log('s3 list: ' . $e->getMessage());
		}

		return [];
	}

	public function find_next(string $marker)
	{
		return $this->list(1, $marker)->current();
	}
}
