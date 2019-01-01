<?php

namespace service;

use Symfony\Component\Finder\Finder;
use service\cache;

class assets
{
	protected $cache;
	protected $file_hash_ary;
	protected $rootpath;
	protected $version = '27';

	const CACHE_HASH_KEY = 'assets_files_hashes';

	const ASSETS_ARY = [
		'bootstrap' => [
			'css'	=> '//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css',
			'js'	=> '//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js',
		],
		'fontawesome'	=> [
			'css'	=> '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
		],
		'footable'	=> [
			'js'	=> [
				'//cdnjs.cloudflare.com/ajax/libs/jquery-footable/2.0.3/js/footable.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jquery-footable/2.0.3/js/footable.sort.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jquery-footable/2.0.3/js/footable.filter.min.js',
			],
			'css'	=> '//cdnjs.cloudflare.com/ajax/libs/jquery-footable/2.0.3/css/footable.core.min.css',
		],
		'jssor'		=> [
			'js'	=> '//cdnjs.cloudflare.com/ajax/libs/jssor-slider/27.5.0/jssor.slider.min.js',
		],
		'jqplot'	=> [
			'js'	=> [
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/jquery.jqplot.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/plugins/jqplot.donutRenderer.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/plugins/jqplot.cursor.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/plugins/jqplot.dateAxisRenderer.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/plugins/jqplot.canvasTextRenderer.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/plugins/jqplot.canvasAxisTickRenderer.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.9/plugins/jqplot.highlighter.min.js',
			],
		],
		'jquery'	=> [
			'js'	=> '//code.jquery.com/jquery-3.3.1.min.js',
		],
		'fileupload'	=> [
			'js'	=>	[

				'//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/js/vendor/jquery.ui.widget.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/js/jquery.iframe-transport.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/blueimp-load-image/2.12.2/load-image.all.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/javascript-canvas-to-blob/3.14.0/js/canvas-to-blob.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/js/jquery.fileupload.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/js/jquery.fileupload-process.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/js/jquery.fileupload-image.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/js/jquery.fileupload-validate.min.js',
			],
			'css'	=> '//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.25.1/css/jquery.fileupload.min.css',
		],
		'typeahead'		=> [
			'js'	=> '//cdnjs.cloudflare.com/ajax/libs/typeahead.js/0.11.1/typeahead.bundle.min.js',
		],
		'autocomplete'	=> [
			'js'	=> [
				'https://cdnjs.cloudflare.com/ajax/libs/algoliasearch/3.30.0/algoliasearch.min.js',
				'https://cdnjs.cloudflare.com/ajax/libs/autocomplete.js/0.31.0/autocomplete.jquery.min.js',
			],
		],
		'datepicker'	=> [
			'js'	=>	[
				'//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/locales/bootstrap-datepicker.nl.min.js',
			],
			'css'	=> '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.standalone.min.css',
		],
		'isotope'	=> [
			'js' => [
				'//cdnjs.cloudflare.com/ajax/libs/jquery.isotope/3.0.6/isotope.pkgd.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jquery.imagesloaded/4.1.4/imagesloaded.pkgd.min.js',
			],
		],
		'leaflet'	=> [
			'js'	=> '//cdnjs.cloudflare.com/ajax/libs/leaflet/1.3.4/leaflet.js',
			'css'	=> '//cdnjs.cloudflare.com/ajax/libs/leaflet/1.3.4/leaflet.css',
		],
		'summernote' => [
			'js'	=> [
				'//cdnjs.cloudflare.com/ajax/libs/summernote/0.8.10/summernote.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/summernote/0.8.10/lang/summernote-nl-NL.min.js',
			],
			'css'	=> '//cdnjs.cloudflare.com/ajax/libs/summernote/0.8.10/summernote.css',
		],
		'swiper' => [
			'js'	=> '//cdnjs.cloudflare.com/ajax/libs/Swiper/4.4.1/js/swiper.jquery.min.js',
			'css'	=> '//cdnjs.cloudflare.com/ajax/libs/Swiper/4.4.1/css/swiper.min.css',
		],
		'sortable' => [
			'js'	=> '//cdnjs.cloudflare.com/ajax/libs/Sortable/1.6.0/Sortable.min.js',
		],
	];

	protected $include_css = [];
	protected $include_css_print = [];
	protected $include_js = [];

	public function __construct(
		cache $cache,
		string $rootpath
	)
	{
		$this->cache = $cache;
		$this->rootpath = $rootpath;

		$this->file_hash_ary = $this->cache->get(self::CACHE_HASH_KEY);
	}

	public function write_file_hash_ary():void
	{
		$finder = new Finder();
		$finder->files()
			->in([
				__DIR__ . '/../gfx',
				__DIR__ . '/../js',
			])
			->name([
				'*.js',
				'*.css',
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

				foreach ($asset as $k => $a)
				{
					if (is_array($a))
					{
						foreach($a as $loc)
						{
							$var = 'include_' . $k;
							$this->$var[] = $loc;
						}

						continue;
					}

					$var = 'include_' . $k;
					$this->$var[] = $a;
				}

				continue;
			}

			$ext = strtolower(pathinfo($asset_name, PATHINFO_EXTENSION));

			if ($ext === 'js')
			{
				$include = $this->rootpath . 'js/' . $asset_name . '?';
				$include .= $this->file_hash_ary[$asset_name];
				$this->include_js[] = $include;
			}
			else if ($ext === 'css')
			{
				$include = $this->rootpath . 'gfx/' . $asset_name . '?';
				$include .= $this->file_hash_ary[$asset_name];
				$this->include_css[] = $include;
			}
		}
	}

	public function add_print_css(array $asset_ary):void
	{
		foreach ($asset_ary as $asset_name)
		{
			$include = $this->rootpath . 'gfx/' . $asset_name . '?';
			$include .= $this->file_hash_ary[$asset_name];
			$this->include_css_print[] = $include;
		}
	}

	public function add_external_css(array $asset_ary):void
	{
		foreach ($asset_ary as $asset_name)
		{
			$this->include_css[] = $asset_file;
		}
	}

	public function get_js():string
	{
		$out = '';

		foreach ($this->include_js as $js)
		{
			$out .= '<script src="' . $js . '"></script>';
		}

		return $out;
	}

	public function get_css():string
	{
		$out = '';

		foreach ($this->include_css as $css)
		{
			$out .= '<link type="text/css" rel="stylesheet" href="' . $css . '" media="screen">';
		}

		foreach ($this->include_css_print as $css)
		{
			$out .= '<link type="text/css" rel="stylesheet" href="' . $css . '" media="print">';
		}

		return $out;
	}

	public function get_version_param():string
	{
		return '?v=' . $this->version;
	}

	public function get_css_print():string
	{
		return $this->include_css_print;
	}
}
