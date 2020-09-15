<?php declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Finder\Finder;
use App\Service\CacheService;

class AssetsService
{
	protected CacheService $cache_service;
	protected array $file_hash_ary = [];
	protected array $include_ary = [];

	const CACHE_HASH_KEY = 'assets_files_hashes';

	const PROVIDER = [
		'cloudflare'	=> 'https://cdnjs.cloudflare.com/ajax/libs/',
	];

	const ASSETS_ARY = [
		'bootstrap' => [ // was 3.3.7
			'css'	=> [
				'twitter-bootstrap/4.4.1/css/bootstrap.min.css',
			],
			'js'	=> [
				'twitter-bootstrap/4.4.1/js/bootstrap.min.js',
			],
		],
		'bootstrap-tagsinput' => [
			'js'	=> [
				'bootstrap-tagsinput/0.8.0/bootstrap-tagsinput.min.js',
			],
		],
		'popper' => [
			'js' => [
				'popper.js/1.16.0/popper.min.js',
			],
		],
		'fontawesome'	=> [
			'css'	=> [
				'font-awesome/4.7.0/css/font-awesome.min.css',
			],
		],
		'footable'	=> [
			'js'	=> [
				'jquery-footable/2.0.3/js/footable.min.js',
				'jquery-footable/2.0.3/js/footable.sort.min.js',
				'jquery-footable/2.0.3/js/footable.filter.min.js',
			],
			'css'	=> [
				'jquery-footable/2.0.3/css/footable.core.min.css',
			],
		],
		'jssor'		=> [
			'js'	=> [
				'jssor-slider/27.5.0/jssor.slider.min.js',
			],
		],
		'jqplot'	=> [
			'js'	=> [
				'jqPlot/1.0.9/jquery.jqplot.min.js',
				'jqPlot/1.0.9/plugins/jqplot.donutRenderer.min.js',
				'jqPlot/1.0.9/plugins/jqplot.cursor.min.js',
				'jqPlot/1.0.9/plugins/jqplot.dateAxisRenderer.min.js',
				'jqPlot/1.0.9/plugins/jqplot.canvasTextRenderer.min.js',
				'jqPlot/1.0.9/plugins/jqplot.canvasAxisTickRenderer.min.js',
				'jqPlot/1.0.9/plugins/jqplot.highlighter.min.js',
			],
		],
		'jquery'	=> [
			'js'	=> [
				'jquery/3.4.1/jquery.min.js',
			],
		],
		'touchswipe' => [
			'js'	=> [
				'jquery.touchswipe/1.6.19/jquery.touchSwipe.min.js',
			],
		],
		'fileupload'	=> [
			'js'	=>	[
				'blueimp-file-upload/9.25.1/js/vendor/jquery.ui.widget.min.js',
				'blueimp-file-upload/9.25.1/js/jquery.iframe-transport.min.js',
				'blueimp-load-image/2.12.2/load-image.all.min.js',
				'javascript-canvas-to-blob/3.14.0/js/canvas-to-blob.min.js',
				'blueimp-file-upload/9.25.1/js/jquery.fileupload.min.js',
				'blueimp-file-upload/9.25.1/js/jquery.fileupload-process.min.js',
				'blueimp-file-upload/9.25.1/js/jquery.fileupload-image.min.js',
				'blueimp-file-upload/9.25.1/js/jquery.fileupload-validate.min.js',
			],
			'css'	=> [
				'blueimp-file-upload/9.25.1/css/jquery.fileupload.min.css',
			],
		],
		'typeahead'		=> [
			'js'	=> [
				'typeahead.js/0.11.1/typeahead.bundle.min.js',
			]
		],
		'datepicker'	=> [
			'js'	=>	[
				'bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js',
				'bootstrap-datepicker/1.8.0/locales/bootstrap-datepicker.nl.min.js',
			],
			'css'	=> [
				'bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.standalone.min.css',
			],
		],
		'isotope'	=> [
			'js' => [
				'jquery.isotope/3.0.6/isotope.pkgd.min.js',
				'jquery.imagesloaded/4.1.4/imagesloaded.pkgd.min.js',
			],
		],
		'leaflet'	=> [
			'js'	=> [
				'leaflet/1.6.0/leaflet.js',
			],
			'css'	=> [
				'leaflet/1.6.0/leaflet.css',
			]
		],
		'summernote' => [
			'js'	=> [
				'summernote/0.8.11/summernote.min.js',
				'summernote/0.8.12/lang/summernote-nl-NL.min.js',
			],
			'css'	=> [
				'summernote/0.8.12/summernote.css',
			],
		],
		'codemirror' => [
			'js'	=> [
				'codemirror/5.48.4/codemirror.min.js',
				'codemirror/5.48.4/addon/dialog/dialog.min.js',
				'codemirror/5.48.4/mode/xml/xml.min.js',
				'codemirror/5.48.4/addon/fold/xml-fold.min.js',
				'codemirror/5.48.4/addon/hint/xml-hint.min.js',
				'codemirror/5.48.4/addon/selection/active-line.min.js',
				'codemirror/5.48.4/addon/search/searchcursor.min.js',
				'codemirror/5.48.4/addon/search/jump-to-line.min.js',
				'codemirror/5.48.4/addon/search/search.min.js',
				'codemirror/5.48.4/addon/edit/matchbrackets.min.js',
				'codemirror/5.48.4/addon/edit/matchtags.min.js',
				'codemirror/5.48.4/addon/edit/trailingspace.min.js',
			],
			'css'	=> [
				'codemirror/5.48.4/codemirror.css',
				'codemirror/5.48.4/theme/monokai.css',
				'codemirror/5.48.4/addon/dialog/dialog.css',
			],
		],
		'sortable' => [
			'js'	=> [
				'Sortable/1.10.2/Sortable.min.js',
			],
		],
	];

	public function __construct(
		CacheService $cache_service
	)
	{
		$this->cache_service = $cache_service;

		$this->file_hash_ary = $this->cache_service->get(self::CACHE_HASH_KEY);
	}

	public function write_file_hash_ary():void
	{
		$finder = new Finder();
		$finder->files()
			->in([
				__DIR__ . '/../../public/css',
				__DIR__ . '/../../public/js',
				__DIR__ . '/../../public/img',
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
		$this->cache_service->set(self::CACHE_HASH_KEY, $this->file_hash_ary);
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
						$this->include_ary[$type][self::PROVIDER['cloudflare'] . $loc] = true;
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
			$this->include_ary['css_print'][$this->get_location($asset_name, 'css')] = true;
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

		foreach ($this->include_ary['css_print'] as $css => $dummy_bool)
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

	public function get_ary(string $type):array
	{
		return array_keys($this->include_ary[$type] ?? []);
	}
}
