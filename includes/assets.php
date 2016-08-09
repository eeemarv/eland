<?php

namespace eland;

class assets
{
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
				'footable-2.0.3/js/footable.js',
				'footable-2.0.3/js/footable.sort.js',
				'footable-2.0.3/js/footable.filter.js',
			],
			'css'	=> 'footable-2.0.3/css/footable.core.css',
		],
		'jssor'		=> [
			'js' => 'jssor/js/jssor.slider.mini.js',
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
			'js'	=> '//code.jquery.com/jquery-2.1.4.min.js',
		],
		'fileupload'	=> [
			'js'	=>	[
				'jQuery-File-Upload-9.10.4/js/vendor/jquery.ui.widget.js',
				'jQuery-File-Upload-9.10.4/js/jquery.iframe-transport.js',
				'JavaScript-Load-Image-1.14.0/js/load-image.all.min.js',
				'JavaScript-Canvas-to-Blob-2.2.0/js/canvas-to-blob.min.js',
				'jQuery-File-Upload-9.10.4/js/jquery.fileupload.js',
				'jQuery-File-Upload-9.10.4/js/jquery.fileupload-process.js',
				'jQuery-File-Upload-9.10.4/js/jquery.fileupload-image.js',
				'jQuery-File-Upload-9.10.4/js/jquery.fileupload-validate.js',
			],
			'css'	=> 'jQuery-File-Upload-9.10.4/css/jquery.fileupload.css',
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
				$this->include_js[] = $pre . $asset_name;
			}
			else if ($ext == 'css')
			{
				$pre = (in_array(substr($asset_name, 0, 2), ['ht', '//'])) ? '' : $this->rootpath . 'gfx/';
				$this->include_css[] = $pre . $asset_name;
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

	public function render_css()
	{
		$out = '';

		foreach ($this->include_css as $css)
		{
			$out .= '<link type="text/css" rel="stylesheet" href="' . $css . '" media="screen">';
		}

		return $out;
	}
}
