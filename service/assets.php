<?php declare(strict_types=1);

namespace service;

use Symfony\Component\Finder\Finder;
use service\cache;

class assets
{
	protected $cache;
	protected $file_hash_ary;

	const CACHE_HASH_KEY = 'assets_files_hashes';

	const PROTOCOL = 'https://';

	const ASSETS_ARY = [
		'bootstrap' => [
			'css'	=> [
				'maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css',
			],
			'js'	=> [
				'maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js',
			],
		],
		'fontawesome'	=> [
			'css'	=> [
				'maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
			],
		],
		'footable'	=> [
			'js'	=> [
				'cdnjs.cloudflare.com/ajax/libs/jquery-footable/2.0.3/js/footable.min.js',
				'cdnjs.cloudflare.com/ajax/libs/jquery-footable/2.0.3/js/footable.sort.min.js',
				'cdnjs.cloudflare.com/ajax/libs/jquery-footable/2.0.3/js/footable.filter.min.js',
			],
			'css'	=> [
				'cdnjs.cloudflare.com/ajax/libs/jquery-footable/2.0.3/css/footable.core.min.css',
			],
		],
		'jssor'		=> [
			'js'	=> [
				'cdnjs.cloudflare.com/ajax/libs/jssor-slider/27.5.0/jssor.slider.min.js',
			],
		],
		'jqplot'	=> [
			'js'	=> [
				'cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/jquery.jqplot.min.js',
				'cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/plugins/jqplot.donutRenderer.min.js',
				'cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/plugins/jqplot.cursor.min.js',
				'cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/plugins/jqplot.dateAxisRenderer.min.js',
				'cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/plugins/jqplot.canvasTextRenderer.min.js',
				'cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/plugins/jqplot.canvasAxisTickRenderer.min.js',
				'cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/plugins/jqplot.highlighter.min.js',
			],
		],
		'jquery'	=> [
			'js'	=> [
				'code.jquery.com/jquery-3.3.1.min.js',
			],
		],
		'fileupload'	=> [
			'js'	=>	[
				'cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/js/vendor/jquery.ui.widget.min.js',
				'cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/js/jquery.iframe-transport.min.js',
				'cdnjs.cloudflare.com/ajax/libs/blueimp-load-image/2.12.2/load-image.all.min.js',
				'cdnjs.cloudflare.com/ajax/libs/javascript-canvas-to-blob/3.14.0/js/canvas-to-blob.min.js',
				'cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/js/jquery.fileupload.min.js',
				'cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/js/jquery.fileupload-process.min.js',
				'cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/js/jquery.fileupload-image.min.js',
				'cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/js/jquery.fileupload-validate.min.js',
			],
			'css'	=> [
				'cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/css/jquery.fileupload.min.css',
			],
		],
		'typeahead'		=> [
			'js'	=> [
				'cdnjs.cloudflare.com/ajax/libs/typeahead.js/0.11.1/typeahead.bundle.min.js',
			]
		],
		'datepicker'	=> [
			'js'	=>	[
				'cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js',
				'cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/locales/bootstrap-datepicker.nl.min.js',
			],
			'css'	=> [
				'cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.standalone.min.css',
			],
		],
		'isotope'	=> [
			'js' => [
				'cdnjs.cloudflare.com/ajax/libs/jquery.isotope/3.0.6/isotope.pkgd.min.js',
				'cdnjs.cloudflare.com/ajax/libs/jquery.imagesloaded/4.1.4/imagesloaded.pkgd.min.js',
			],
		],
		'leaflet'	=> [
			'js'	=> [
				'cdnjs.cloudflare.com/ajax/libs/leaflet/1.3.4/leaflet.js',
			],
			'css'	=> [
				'cdnjs.cloudflare.com/ajax/libs/leaflet/1.3.4/leaflet.css',
			]
		],
		'summernote' => [
			'js'	=> [
				'cdnjs.cloudflare.com/ajax/libs/summernote/0.8.10/summernote.min.js',
				'cdnjs.cloudflare.com/ajax/libs/summernote/0.8.10/lang/summernote-nl-NL.min.js',
			],
			'css'	=> [
				'cdnjs.cloudflare.com/ajax/libs/summernote/0.8.12/summernote.css',
			],
		],
		'sortable' => [
			'js'	=> [
				'cdnjs.cloudflare.com/ajax/libs/Sortable/1.6.0/Sortable.min.js',
			],
		],
	];

	protected $include_ary = [];
	protected $include_css_print_ary = [];

	public function __construct(
		cache $cache
	)
	{
		$this->cache = $cache;

		$this->file_hash_ary = $this->cache->get(self::CACHE_HASH_KEY);
	}

	public function write_file_hash_ary():void
	{
		$finder = new Finder();
		$finder->files()
			->in([
				__DIR__ . '/../web/css',
				__DIR__ . '/../web/js',
				__DIR__ . '/../web/img',
			])
			->name([
				'*.js',
				'*.css',
				'*.png',
				'*.gif',
				'*.jpg',
				'*.jpeg',
			]);

		error_log('+-----------------------+');
		error_log('| Set hashes for assets |');
		error_log('+-----------------------+');

		$new_file_hash_ary = [];

		foreach ($finder as $file)
		{
			$contents = $file->getContents();
			$crc = crc32($contents);
			$name = $file->getRelativePathname();

			if (!isset($this->file_hash_ary[$name]))
			{
				$comment = 'NEW';
			}
			else if ($this->file_hash_ary[$name] !== $crc)
			{
				$comment = 'NEW hash, OLD: ' . $this->file_hash_ary[$name];
			}
			else
			{
				$comment = 'unchanged';
			}

			error_log($name . ' :: ' . $crc . ' ' . $comment);

			$new_file_hash_ary[$name] = $crc;
		}

		$this->file_hash_ary = $new_file_hash_ary;
		$this->cache->set(self::CACHE_HASH_KEY, $this->file_hash_ary);
	}

	public function add(array $asset_ary):void
	{
		foreach($asset_ary as $asset_name)
		{
			if (isset(self::ASSETS_ARY[$asset_name]))
			{
				$asset = self::ASSETS_ARY[$asset_name];

				foreach ($asset as $type => $ary)
				{
					if ($type !== 'js' && $type !== 'css')
					{
						continue;
					}

					foreach ($ary as $loc)
					{
						$this->include_ary[$type][self::PROTOCOL . $loc] = true;
					}
				}

				continue;
			}

			$type = $this->get_type($asset_name);
			$this->include_ary[$type][$this->get_location($asset_name, $type)] = true;
		}
	}

	private function get_type(string $asset_name):string
	{
		$ext = strtolower(pathinfo($asset_name, PATHINFO_EXTENSION));
		return  $ext === 'js' || $ext === 'css' ? $ext : 'img';
	}

	private function get_location(string $asset_name, string $type):string
	{
		return '/' . $type . '/' . $asset_name . '?' . $this->file_hash_ary[$asset_name];
	}

	public function add_print_css(array $asset_ary):void
	{
		foreach ($asset_ary as $asset_name)
		{
			$this->include_css_print_ary[$this->get_location($asset_name, 'css')] = true;
		}
	}

	public function add_external_css(array $asset_ary):void
	{
		foreach ($asset_ary as $asset_name)
		{
			$this->include['css'][] = $asset_name;
		}
	}

	public function get_js():string
	{
		$out = '';

		foreach ($this->include_ary['js'] as $js => $dummy_bool)
		{
			$out .= '<script src="' . $js . '"></script>';
		}

		return $out;
	}

	public function get_css():string
	{
		$out = '';

		foreach ($this->include_ary['css'] as $css => $dummy_bool)
		{
			$out .= '<link type="text/css" rel="stylesheet" href="' . $css . '" media="screen">';
		}

		foreach ($this->include_css_print_ary as $css => $dummy_bool)
		{
			$out .= '<link type="text/css" rel="stylesheet" href="' . $css . '" media="print">';
		}

		return $out;
	}

	public function get(string $asset_name):string
	{
		$type = $this->get_type($asset_name);
		return $this->get_location($asset_name, $type);
	}
}
