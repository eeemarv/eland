<?php

namespace eland;

class assets
{
	private $version = '1';

	private $asset_ary = [
		'bootstrap' => [
			'css'	=> '//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css',
			'js'	=> '//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js',
		],
		'fontawesome'	=> [
			'css'	=> '//maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css',
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
			'js'	=> '//cdnjs.cloudflare.com/ajax/libs/jssor-slider/21.1.5/jssor.slider.min.js',
		],
		'jqplot'	=> [
			'js'	=> [
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/jquery.jqplot.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/plugins/jqplot.donutRenderer.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/plugins/jqplot.cursor.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/plugins/jqplot.dateAxisRenderer.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/plugins/jqplot.canvasTextRenderer.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/plugins/jqplot.canvasAxisTickRenderer.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/plugins/jqplot.highlighter.min.js',
			],
		],
		'jquery'	=> [
//			'js'	=> '//code.jquery.com/jquery-2.1.4.min.js',
			'js'	=> '//cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js',
		],
		'fileupload'	=> [
			'js'	=>	[

				'//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.14.1/js/vendor/jquery.ui.widget.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.14.1/js/jquery.iframe-transport.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/blueimp-load-image/2.10.0/load-image.all.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/javascript-canvas-to-blob/3.6.0/js/canvas-to-blob.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.14.1/js/jquery.fileupload.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.14.1/js/jquery.fileupload-process.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.14.1/js/jquery.fileupload-image.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.14.1/js/jquery.fileupload-validate.min.js',
			],
			'css'	=> '//cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.14.1/css/jquery.fileupload.min.css',
		],
		'typeahead'		=> [
			'js'	=> '//cdnjs.cloudflare.com/ajax/libs/typeahead.js/0.11.1/typeahead.bundle.min.js',
		],
		'datepicker'	=> [
			'js'	=>	[
				'//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.6.1/js/bootstrap-datepicker.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.6.1/locales/bootstrap-datepicker.nl.min.js',
			],
			'css'	=> '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.6.1/css/bootstrap-datepicker.standalone.min.css',
		],
		'isotope'	=> [
			'js' => [
				'//cdnjs.cloudflare.com/ajax/libs/jquery.isotope/2.2.2/isotope.pkgd.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/jquery.imagesloaded/3.2.0/imagesloaded.pkgd.min.js',
			],
		],
		'leaflet'	=> [
			'js'	=> '//cdn.leafletjs.com/leaflet/v0.7.7/leaflet.js',
			'css'	=> '//cdn.leafletjs.com/leaflet/v0.7.7/leaflet.css',
		],
		'leaflet_label' => [
			'js'	=> '//api.mapbox.com/mapbox.js/plugins/leaflet-label/v0.2.1/leaflet.label.js',
			'css'	=> '//api.mapbox.com/mapbox.js/plugins/leaflet-label/v0.2.1/leaflet.label.css',
		],
		'summernote' => [
			'js'	=> [
				'//cdnjs.cloudflare.com/ajax/libs/summernote/0.8.1/summernote.min.js',
				'//cdnjs.cloudflare.com/ajax/libs/summernote/0.8.1/lang/summernote-nl-NL.min.js',
			],
			'css'	=> '//cdnjs.cloudflare.com/ajax/libs/summernote/0.8.1/summernote.css',
		],
	];

	private $include_css = [];
	private $include_css_print = [];
	private $include_js = [];

	private $res_url = '';

	/**
	 *
	 */

	public function __construct(string $res_url, string $rootpath)
	{
		$this->res_url = $res_url;
		$this->rootpath = $rootpath;
	}

	/*
	 *
	 */

	public function add($asset_s)
	{
		$asset_ary = is_array($asset_s) ? $asset_s : [$asset_s];

		foreach($asset_ary as $asset_name)
		{
			if (isset($this->asset_ary[$asset_name]))
			{
				$asset = $this->asset_ary[$asset_name];

				foreach ($asset as $k => $a)
				{
					if (is_array($a))
					{
						foreach($a as $loc)
						{
							$pre = (substr($loc, 0, 2) == '//') ? '' : $this->res_url;
							$var = 'include_' . $k;
							$this->$var[] = $pre . $loc;
						}

						continue;
					}

					$pre = (substr($a, 0, 2) == '//') ? '' : $this->res_url;
					$var = 'include_' . $k;
					$this->$var[] = $pre . $a;
				}

				continue;
			}

			$ext = strtolower(pathinfo($asset_name, PATHINFO_EXTENSION));

			if ($ext == 'js')
			{
				$pre = (in_array(substr($asset_name, 0, 2), ['ht', '//'])) ? '' : $this->rootpath . 'js/';
				$this->include_js[] = $pre . $asset_name . '?v=' . $this->version;
			}
			else if ($ext == 'css')
			{
				$pre = (in_array(substr($asset_name, 0, 2), ['ht', '//'])) ? '' : $this->rootpath . 'gfx/';

				if (strpos($asset_name, 'print') !== false)
				{
					$this->include_css_print[] = $pre . $asset_name . '?v=' . $this->version;
					continue;
				}

				$this->include_css[] = $pre . $asset_name . '?v=' . $this->version;
			}
		}

		return $this;
	}

	/*
	*
	*/

	public function render_js()
	{
		$out = '';

		foreach ($this->include_js as $js)
		{
			$out .= '<script src="' . $js . '"></script>';
		}

		return $out;
	}

	/*
	 *
	 */

	public function get_js()
	{
		return $this->include_js;
	}

	/*
	*
	*/

	public function render_css()
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

	/**
	 *
	 */

	public function get_version_param()
	{
		return '?v=' . $this->version;
	}

	/*
	 *
	 */

	public function get_css()
	{
		return $this->include_css;
	}

	/*
	 *
	 */

	public function get_css_print()
	{
		return $this->include_css_print;
	}
}
