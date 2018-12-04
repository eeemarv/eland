<?php

namespace service;

use Aws\S3\S3Client;

class s3
{
	protected $img_bucket;
	protected $doc_bucket;
	protected $client;

	protected $img_types = [
		'jpg'	=> 'image/jpeg',
		'jpeg'	=> 'image/jpeg',
		'png'	=> 'image/png',
		'gif'	=> 'image/gif',
	];

	protected $doc_types = [
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
	];

	public function __construct(
		string $img_bucket,
		string $doc_bucket
	)
	{
		$this->img_bucket = $img_bucket;
		$this->doc_bucket = $doc_bucket;

		$this->client = S3Client::factory([
			'signature'	=> 'v4',
			'region'	=> 'eu-central-1',
			'version'	=> '2006-03-01',
		]);
	}

	public function img_exists(string $filename):bool
	{
		return $this->client->doesObjectExist($this->img_bucket, $filename);
	}

	public function doc_exists(string $filename):bool
	{
		return $this->client->doesObjectExist($this->doc_bucket, $filename);
	}

	public function img_upload(string $filename, string $tmpfile)
	{
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		$content_type = $this->img_types[$ext] ?? false;

		if (!$content_type)
		{
			return 'Geen geldig bestandstype.';
		}

		try {

			$this->client->upload($this->img_bucket, $filename, fopen($tmpfile, 'rb'), 'public-read', [
				'params'	=> [
					'CacheControl'	=> 'public, max-age=31536000',
					'ContentType'	=> $content_type,
				],
			]);

			return;
		}
		catch(Exception $e)
		{
			return 'Opladen mislukt: ' . $e->getMessage();
		}
	}

	public function doc_upload(string $filename, string $tmpfile)
	{
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		$content_type = $this->doc_types[$ext] ?? false;

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
			$this->client->upload($this->doc_bucket, $filename, fopen($tmpfile, 'rb'), 'public-read', [
				'params'	=> [
					'CacheControl'	=> 'public, max-age=31536000',
					'ContentType'	=> $content_type,
				],
			]);
		}
		catch(Exception $e)
		{
			return 'Opladen mislukt: ' . $e->getMessage();
		}
	}

	public function img_copy(string $source, string $destination)
	{
		return $this->copy($this->img_bucket, $source, $destination);
	}

	public function doc_copy(string $source, string $destination)
	{
		return $this->copy($this->doc_bucket, $source, $destination);
	}

	public function copy(string $bucket, string $source, string $destination)
	{
		try
		{
			$result = $this->client->getObject([
				'Bucket' => $bucket,
				'Key'    => $source,
			]);

			$this->client->copyObject([
				'Bucket'		=> $bucket,
				'CopySource'	=> $bucket . '/' . $source,
				'Key'			=> $destination,
				'ACL'			=> 'public-read',
				'CacheControl'	=> 'public, max-age=31536000',
				'ContentType'	=> $result['ContentType'],
			]);
		}
		catch (Exception $e)
		{
			return 'Kopiëren mislukt: ' . $e->getMessage();
		}
	}

	public function img_del(string $filename)
	{
		return $this->del($this->img_bucket, $filename);
	}

	public function doc_del(string $filename)
	{
		return $this->del($this->doc_bucket, $filename);
	}

	public function del(string $bucket, string $filename)
	{
		try
		{
			$this->client->deleteObject([
				'Bucket'	=> $bucket,
				'Key'		=> $filename,
			]);
		}
		catch (Exception $e)
		{
			return 'Verwijderen mislukt: ' . $e->getMessage();
		}
	}

	public function img_list(string $marker = '0')
	{
		return $this->bucket_list($this->img_bucket, $marker);
	}

	public function doc_list(string $marker = '0')
	{
		return $this->bucket_list($this->doc_bucket, $marker);
	}

	public function bucket_list(string $bucket, $marker = '0')
	{
		$params = ['Bucket'	=> $bucket];

		if ($marker)
		{
			$params['Marker'] = $marker;
		}

		try
		{
			$objects = $this->client->getIterator('ListObjects', $params);

			return $objects;
		}
		catch (Exception $e)
		{
			echo $e->getMessage() . "\n\r\n\r";
		}
	}

	public function find_img(string $marker = '0')
	{
		$params = [
			'Bucket'	=> $this->img_bucket,
			'Marker'	=> $marker,
			'MaxKeys'	=> 1,
		];

		try
		{
			return $this->client->getIterator('ListObjects', $params)->current();
		}
		catch (Exception $e)
		{
			echo $e->getMessage() . "\n\r\n\r";
			return [];
		}
	}
}
