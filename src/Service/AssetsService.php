<?php declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AssetsService
{
	const CACHE_KEY = 'asset_hashes';
	const CACHE_TTL = 31536000; // 1 year;
	const CACHE_BETA = 1;

	const PROVIDER = [
		'cloudflare'	=> 'https://cdnjs.cloudflare.com/ajax/libs/',
	];

	const ASSETS_ARY = [
		'bootstrap' => [
			'css'	=> [
				'twitter-bootstrap/3.3.7/css/bootstrap.min.css',
			],
			'js'	=> [
				'twitter-bootstrap/3.3.7/js/bootstrap.min.js',
			],
		],
		'tagsinput' => [
			'js'	=> [
				'bootstrap-tagsinput/0.8.0/bootstrap-tagsinput.min.js',
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
				'jssor-slider/28.0.0/jssor.slider.min.js',
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
				'jquery/3.6.0/jquery.min.js',
			],
		],
		'fileupload'	=> [
			'js'	=>	[
				'blueimp-file-upload/10.32.0/js/vendor/jquery.ui.widget.min.js',
				'blueimp-file-upload/10.32.0/js/jquery.iframe-transport.min.js',
				'blueimp-load-image/5.16.0/load-image.all.min.js',
				'javascript-canvas-to-blob/3.29.0/js/canvas-to-blob.min.js',
				'blueimp-file-upload/10.32.0/js/jquery.fileupload.min.js',
				'blueimp-file-upload/10.32.0/js/jquery.fileupload-process.min.js',
				'blueimp-file-upload/10.32.0/js/jquery.fileupload-image.min.js',
				'blueimp-file-upload/10.32.0/js/jquery.fileupload-validate.min.js',
			],
			'css'	=> [
				'blueimp-file-upload/10.32.0/css/jquery.fileupload.min.css',
			],
		],
		'typeahead'		=> [
			'js'	=> [
				'typeahead.js/0.11.1/typeahead.bundle.min.js',
			]
		],
		'datepicker'	=> [
			'js'	=>	[
				'bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js',
				'bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.nl.min.js',
			],
			'css'	=> [
				'bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.standalone.min.css',
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
				'leaflet/1.9.3/leaflet.js',
			],
			'css'	=> [
				'leaflet/1.9.3/leaflet.css',
			]
		],
		'summernote' => [
			'js'	=> [
				'summernote/0.8.20/summernote.min.js',
				'summernote/0.8.20/lang/summernote-nl-NL.min.js',
			],
			'css'	=> [
				'summernote/0.8.20/summernote.css',
			],
		],
		'codemirror' => [
			'js'	=> [
				'codemirror/5.65.12/codemirror.min.js',
				'codemirror/5.65.12/addon/dialog/dialog.min.js',
				'codemirror/5.65.12/mode/xml/xml.min.js',
				'codemirror/5.65.12/mode/css/css.min.js',
				'codemirror/5.65.12/mode/javascript/javascript.min.js',
				'codemirror/5.65.12/mode/htmlmixed/htmlmixed.min.js ',
				'codemirror/5.65.12/addon/fold/xml-fold.min.js',
				'codemirror/5.65.12/addon/hint/xml-hint.min.js',
				'codemirror/5.65.12/addon/selection/active-line.min.js',
				'codemirror/5.65.12/addon/search/searchcursor.min.js',
				'codemirror/5.65.12/addon/search/jump-to-line.min.js',
				'codemirror/5.65.12/addon/search/search.min.js',
				'codemirror/5.65.12/addon/edit/matchbrackets.min.js',
				'codemirror/5.65.12/addon/edit/closebrackets.min.js',
				'codemirror/5.65.12/addon/edit/matchtags.min.js',
				'codemirror/5.65.12/addon/edit/closetag.min.js',
				'codemirror/5.65.12/addon/edit/trailingspace.min.js',
			],
			'css'	=> [
				'codemirror/5.65.12/codemirror.min.css',
				'codemirror/5.65.12/theme/monokai.min.css',
				'codemirror/5.65.12/addon/dialog/dialog.min.css',
			],
		],
		'sortable' => [
			'js'	=> [
				'Sortable/1.15.0/Sortable.min.js',
			],
		],
	];

	protected array $file_hash_ary;
	protected array $include_ary = [];

	public function __construct(
		protected TagAwareCacheInterface $cache,
		protected ResponseCacheService $response_cache_service,
		protected UrlGeneratorInterface $url_generator,
		protected SystemsService $systems_service
	)
	{
	}

	private function get_file_hash_ary():array
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
				'*.webp',
			]);

		error_log('+-----------------------+');
		error_log('| Get hashes for assets |');
		error_log('+-----------------------+');

		$file_hash_ary = [];

		foreach ($finder as $file)
		{
			$contents = $file->getContents();
			$hash = hash('crc32b', $contents);
			$name = $file->getRelativePathname();

			error_log($name . ' :: ' . $hash);

			$file_hash_ary[$name] = $hash;
		}

		return $file_hash_ary;
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
		if (!isset($this->file_hash_ary))
		{
			$this->file_hash_ary = $this->cache->get(self::CACHE_KEY, function(ItemInterface $item){

				$item->tag(['deploy', 'assets']);
				$item->expiresAfter(self::CACHE_TTL);

				return $this->get_file_hash_ary();

			}, self::CACHE_BETA);
		}

		return '/' . $type . '/' . $asset_name . '?' . $this->file_hash_ary[$asset_name];
	}

	public function add_var_css(string $thumbprint_key, string $schema):void
	{
		[$route, $type] = explode('.', $thumbprint_key);
		$thumbprint = $this->response_cache_service->get_thumbprint_from_key($thumbprint_key, $schema);
		$params = [
			'thumbprint'	=> $thumbprint,
			'system'		=> $this->systems_service->get_system($schema),
		];
		switch ($route)
		{
			case 'tags_css':
				$params['tag_type'] = $type;
				break;
			default:
				return;
				break;
		}
		$url = $this->url_generator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_PATH);
		$this->include_ary['css_var'][$url] = true;
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

		foreach ($this->include_ary['css_var'] as $css => $dummy_bool)
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
