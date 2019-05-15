<?php

$page_access = 'admin';
require_once __DIR__ . '/include/web.php';

use util\cnst_config;

$setting = $_GET['edit'] ?? false;
$submit = isset($_POST['zend']) ? true : false;

$active_tab = 'systemname';
$active_tab = $_GET['tab'] ?? $active_tab;
$active_tab = $_POST['tab'] ?? $active_tab;

if (!isset(cnst_config::TAB_PANES[$active_tab]))
{
	http_response_code(404);
	echo $app['twig']->render('404.html.twig');
	exit;
}

$pane = cnst_config::TAB_PANES[$active_tab];

$cond_ary = [
	'config_template_lets'	=> true,
];

$block_ary = cnst_config::BLOCK_ARY;

if (!$app['config']->get('forum_en', $app['tschema']))
{
	unset($block_ary['periodic_mail']['forum']);
}

if (!$app['config']->get('interlets_en', $app['tschema'])
	|| !$app['config']->get('template_lets', $app['tschema']))
{
	unset($block_ary['periodic_mail']['interlets']);
	unset($cond_ary['config_template_lets']);
}

$select_options = [
	'date_format'	=> $app['date_format']->get_options(),
	'landing_page'	=> cnst_config::LANDING_PAGE_OPTIONS,
];

$explain_replace_ary = [
	'%path_register%'	=> $app['base_url'] . '/register.php',
	'%path_contact%'	=> $app['base_url'] . '/contact.php',
];

$addon_replace_ary = [
	'%config_currency%'	=> $app['config']->get('currency', $app['tschema']),
];

$attr_replace_ary = [
	'%map_template_vars%'	=> implode(',', array_keys(cnst_config::MAP_TEMPLATE_VARS)),
];

$config = [];

foreach ($pane['inputs'] as $input_name => $input_config)
{
	if (is_array($input_config) && isset($input_config['inline']))
	{
		$inline_input_names = get_tag_ary('input', $input_config['inline']);

		foreach ($inline_input_names as $inline_input_name)
		{
			$config[$inline_input_name] = $app['config']->get($inline_input_name, $app['tschema']);
		}

		continue;
	}

	$config[$input_name] = $app['config']->get($input_name, $app['tschema']);
}

if ($app['is_http_post'])
{
	if (!isset($_POST[$active_tab . '_submit']))
	{
		$errors[] = 'Form submit error';
	}

	if ($error_token = $app['form_token']->get_error())
	{
		$errors[] = $error_token;
	}

	$posted_configs = [];

	foreach ($config as $input_name => $loaded_value)
	{
		$posted_value = trim($_POST[$input_name] ?? '');
		$input_data = cnst_config::INPUTS[$input_name];

		if (isset($input_data['cond']) &&
			!isset($cond_ary[$input_data['cond']]))
		{
			continue;
		}

		$validator = [
			'type'			=> $input_data['type'] ?? 'text',
			'attr'			=> $input_data['attr'] ?? [],
			'required'		=> isset($input_data['required']) ? true : false,
			'max_inputs' 	=> $input_data['max_inputs'] ?? 1,
		];

		$err_n = ' (' . $input_name . ')';

		if ($validator['required']
			&& $posted_value === '')
		{
			$errors[] = 'Het veld is verplicht in te vullen.' . $err_n;
			continue;
		}

		if ($validator['type'] === 'text'
			|| $validator['type'] === 'textarea')
		{
			$config_htmlpurifier = HTMLPurifier_Config::createDefault();
			$config_htmlpurifier->set('Cache.DefinitionImpl', null);
			$htmlpurifier = new HTMLPurifier($config_htmlpurifier);
			$posted_value = $htmlpurifier->purify($posted_value);
		}

		$posted_value = strip_tags($posted_value) !== '' ? $posted_value : '';

		if ($validator['type'] === 'checkbox')
		{
			$posted_value = $posted_value ? '1' : '0';
		}

		if ($posted_value === $config[$input_name])
		{
			continue;
		}

		$posted_configs[$input_name] = $posted_value;

		if ($input_name === 'date_format')
		{
			$error = $app['date_format']->get_error($posted_value);

			if ($error)
			{
				$errors[] = $error . $err_n;
			}

			continue;
		}

		if ($validator['type'] === 'text'
			|| $validator['type'] === 'textarea')
		{
			if (isset($validator['attr']['maxlength'])
				&& strlen($posted_value) > $validator['attr']['maxlength'])
			{
				$errors[] = 'Fout: de waarde mag maximaal ' .
					$validator['attr']['maxlength'] .
					' tekens lang zijn.' . $err_n;
			}

			if (isset($validator['attr']['minlength'])
				&& strlen($posted_value) < $validator['attr']['minlength'])
			{
				$errors[] = 'Fout: de waarde moet minimaal ' .
					$validator['attr']['minlength'] .
					' tekens lang zijn.' . $err_n;
			}

			continue;
		}

		if ($validator['type'] === 'number')
		{
			if ($posted_value === '' && !$validator['required'])
			{
				continue;
			}

			if (!filter_var($posted_value, FILTER_VALIDATE_INT))
			{
				$errors[] = 'Fout: de waarde moet een getal zijn.' . $err_n;
			}

			if (isset($validator['attr']['max'])
				&& $posted_value > $validator['attr']['max'])
			{
				$errors[] = 'Fout: de waarde mag maximaal ' .
					$validator['attr']['max'] .
					' bedragen.' . $err_n;
			}

			if (isset($validator['attr']['min'])
				&& $posted_value < $validator['attr']['min'])
			{
				$errors[] = 'Fout: de waarde moet minimaal ' .
					$validator['attr']['min'] .
					' bedragen.' . $err_n;
			}

			continue;
		}

		if ($validator['type'] === 'checkbox')
		{
			continue;
		}

		if ($validator['type'] === 'email')
		{
			if (isset($validator['max_inputs']))
			{
				$mail_ary = explode(',', $posted_value);

				if (count($mail_ary) > $validator['max_inputs'])
				{
					$errors[] = 'Maximaal ' .
						$validator['max_inputs'] .
						' E-mail adressen mogen ingegeven worden.' .
						$err_n;
				}

				foreach ($mail_ary as $m)
				{
					$m = trim($m);

					if (!filter_var($m, FILTER_VALIDATE_EMAIL))
					{
						$errors[] =  $m . ' is geen geldig E-mail adres.' . $err_n;
					}
				}

				continue;
			}

			if (!filter_var($posted_value, FILTER_VALIDATE_EMAIL))
			{
				$errors[] =  $posted_value . ' is geen geldig E-mail adres.' . $err_n;
			}

			continue;
		}

		if ($validator['type'] === 'url')
		{
			if ($posted_value != '')
			{
				if (!filter_var($posted_value, FILTER_VALIDATE_URL))
				{
					$errors[] =  $posted_value . ' is geen geldig url adres.' . $err_n;
				}
			}

			continue;
		}
	}

	if (!count($posted_configs))
	{
		$app['alert']->warning('Geen gewijzigde waarden.');
		cancel($active_tab);
	}

	if (count($errors))
	{
		$app['alert']->error($errors);
		cancel($active_tab);
	}

	$execute_post_actions = [];

	foreach ($posted_configs as $input_name => $posted_value)
	{
		$app['config']->set($input_name, $app['tschema'], $posted_value);

		// prevent string too long error for eLAS database

		if (isset(cnst_config::INPUTS[$input_name]['max_inputs'])
			&& cnst_config::INPUTS[$input_name]['max_inputs'] > 1)
		{
			[$posted_value] = explode(',', $posted_value);
			$posted_value = trim($posted_value);
		}

		$posted_value = substr($posted_value, 0, 60);

		$app['db']->update($app['tschema'] . '.config',
			['value' => $posted_value, '"default"' => 'f'],
			['setting' => $input_name]);

		$post_actions = cnst_config::INPUTS[$input_name]['post_actions'] ?? [];

		foreach($post_actions as $post_action)
		{
			$execute_post_actions[$post_action] = true;
		}
	}

	if (isset($execute_post_actions['clear_eland_intersystem_cache']))
	{
		$app['intersystems']->clear_eland_cache();
	}

	if (count($posted_configs) > 1)
	{
		$app['alert']->success('De instellingen zijn aangepast.');
	}
	else
	{
		$app['alert']->success('De instelling is aangepast.');
	}

	cancel($active_tab);
}

if (isset(cnst_config::TAB_PANES[$active_tab]['assets']))
{
	$app['assets']->add(cnst_config::TAB_PANES[$active_tab]['assets']);
}

$h1 = 'Instellingen';
$fa = 'gears';

include __DIR__ . '/include/header.php';

echo '<div>';
echo '<ul class="nav nav-pills">';

foreach (cnst_config::TAB_PANES as $tab_id => $tab_pane_data)
{
	echo '<li role="presentation"';
	echo $tab_id === $active_tab ? ' class="active"' : '';
	echo '>';
	echo aphp('config',
		['tab' => $tab_id],
		$tab_pane_data['lbl']);
	echo '</li>';
}

echo '</ul>';

echo '<form method="post">';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading"><h4>';
echo $pane['lbl_pane'] ?? $pane['lbl'];
echo '</h4>';

if (isset($pane['explain']))
{
	echo '<p>';
	echo $pane['explain'];
	echo '</p>';
}

echo '</div>';

echo '<ul class="list-group">';

foreach ($pane['inputs'] as $pane_input_name => $pane_input_value)
{
	if (is_array($pane_input_value))
	{
		$input = $pane_input_value;
	}
	else
	{
		$input = cnst_config::INPUTS[$pane_input_name];
		$input_name = $pane_input_name;
	}

	if (isset($input['cond']) &&
	!isset($cond_ary[$input['cond']]))
	{
		continue;
	}

	echo '<li class="list-group-item bg-info">';

	if (isset($input['max_inputs']) && $input['max_inputs'] > 1)
	{
		echo '<input type="hidden" value="';
		echo $config[$input_name];
		echo '" ';
		echo 'data-max-inputs="';
		echo $input['max_inputs'];
		echo '" ';
		echo 'name="';
		echo $input_name;
		echo '">';

		$name_suffix = '_0';
	}
	else
	{
		$name_suffix = '';
	}

	if (isset($input['inline']))
	{
		$search_inline_ary = [];
		$replace_inline_ary = [];
		$id_for_label = '';

		$inline_input_names = get_tag_ary('input', $input['inline']);

		foreach ($inline_input_names as $inline_input_name)
		{
			$inline_input_data = cnst_config::INPUTS[$inline_input_name];

			$str = '<input type="';
			$str .= $inline_input_data['type'] ?? 'text';
			$str .= '" name="';
			$str .= $inline_input_name;
			$str .= '"';

			if (!$id_for_label)
			{
				$id_for_label = 'inline_id_' . $inline_input_name;
				$str .= ' id="' . $id_for_label . '"';
			}

			if ($inline_input_data['type'] == 'checkbox')
			{
				$str .= ' value="1"';
				$str .= $config[$inline_input_name] ? ' checked="checked"' : '';
			}
			else
			{
				$str .= ' class="sm-size"';
				$str .= ' value="';
				$str .= $config[$inline_input_name];
				$str .= '"';
			}

			if (isset($inline_input_data['attr']))
			{
				foreach ($inline_input_data['attr'] as $attr_name => $attr_value)
				{
					$str .= ' ';
					$str .= $attr_name;
					$str .= '="';
					$str .=  strtr($attr_value, $attr_replace_ary);
					$str .= '"';
				}
			}

			$str .= isset($inline_input_data['required']) ? ' required' : '';

			$str .= '>';

			$search_inline = cnst_config::TAG['input']['open'];
			$search_inline .= $inline_input_name;
			$search_inline .= cnst_config::TAG['input']['close'];

			$replace_inline_ary[$search_inline] = $str;
		}

		echo '<p>';

		if ($id_for_label)
		{
			echo '<label for="';
			echo $id_for_label;
			echo '">';
		}

		echo strtr($input['inline'], $replace_inline_ary);

		if ($id_for_label)
		{
			echo '</label>';
		}

		echo '</p>';
	}
	else if (isset($input['type'])
		&& $input['type'] === 'sortable'
		&& isset($input['block_ary']))
	{
		$v_options = $active = $inactive = [];
		$value_ary = explode(',', ltrim($config[$input_name], '+ '));

		foreach ($value_ary as $val)
		{
			[$block, $option] = explode('.', $val);
			$v_options[$block] = $option;
			$active[] = $block;
		}

		foreach ($block_ary[$input['block_ary']] as $block => $options)
		{
			if (!isset($v_options[$block]))
			{
				$inactive[] = $block;
			}
		}

		echo isset($input['lbl']) ? '<h4>' . $input['lbl'] . '</h4>' : '';

		if (isset($input['explain_top']))
		{
			echo '<p>';
			echo $input['explain_top'];
			echo '</p>';
		}

		echo '<div class="row">';

		echo '<div class="col-md-6">';
		echo '<div class="panel panel-default">';
		echo '<div class="panel-heading">';

		if (isset($input['lbl_active']))
		{
			echo '<h5>';
			echo $input['lbl_active'];
			echo '</h5>';
		}

		echo '</div>';
		echo '<div class="panel-body">';
		echo '<ul id="list_active" class="list-group">';

		echo get_sortable_items_str(
			$block_ary[$input['block_ary']],
			$v_options,
			$active,
			'bg-success');

		echo '</ul>';
		echo '</div>';
		echo '</div>';
		echo '</div>'; // col

		echo '<div class="col-md-6">';
		echo '<div class="panel panel-default">';
		echo '<div class="panel-heading">';

		if (isset($input['lbl_inactive']))
		{
			echo '<h5>';
			echo $input['lbl_inactive'];
			echo '</h5>';
		}

		echo '</div>';
		echo '<div class="panel-body">';
		echo '<ul id="list_inactive" class="list-group">';

		echo get_sortable_items_str(
			$block_ary[$input['block_ary']],
			$v_options,
			$inactive,
			'bg-danger');

		echo '</ul';
		echo '</div>';
		echo '</div>';
		echo '</div>'; // col

		echo '</div>'; // row

		echo '<input type="hidden" name="';
		echo $input_name;
		echo '" ';
		echo 'value="';
		echo $config[$input_name];
		echo '" id="';
		echo $input_name;
		echo '">';
	}
	else
	{
		echo '<div class="form-group">';

		if (isset($input['lbl']))
		{
			echo '<label class="control-label">';
			echo $input['lbl'];
			echo '</label>';
		}

		if (isset($input['explain_top']))
		{
			echo '<p>';
			echo $input['explain_top'];
			echo '</p>';
		}

		if (isset($input['addon']) || isset($input['addon_fa']))
		{
			echo '<div class="input-group">';
			echo '<span class="input-group-addon">';

			if (isset($input['addon']))
			{
				echo strtr($input['addon'], $addon_replace_ary);
			}

			if (isset($input['addon_fa']))
			{
				echo '<i class="fa fa-';
				echo $input['addon_fa'];
				echo '"></i>';
			}

			echo '</span>';
		}

		if (isset($input['type']) && $input['type'] === 'select')
		{
			echo '<select class="form-control" name="';
			echo $input_name . '"';
			echo isset($input['required']) ? ' required' : '';
			echo '>';

			echo get_select_options($select_options[$input['options']],
				$config[$input_name]);

			echo '</select>';
		}
		else if (isset($input['type']) && $input['type'] === 'textarea')
		{
			echo '<textarea name="' . $input_name . '" ';
			echo 'id="' . $input_name . '" class="form-control';
			echo isset($input['rich_edit']) ? ' rich-edit' : '';
			echo '" rows="4"';

			echo isset($input['attr']['maxlength']) ? '' : ' maxlength="2000"';
			echo isset($input['attr']['minlength']) ? '' : ' minlength="1"';
			echo isset($input['required']) ? ' required' : '';

			if (isset($input['attr']))
			{
				foreach ($input['attr'] as $attr_name => $attr_value)
				{
					echo ' ' . $attr_name . '="';
					echo strtr($attr_value, $attr_replace_ary);
					echo '"';
				}
			}

			echo '>';
			echo $config[$input_name];
			echo '</textarea>';
		}
		else
		{
			echo '<input type="';
			echo $input['type'] ?? 'text';
			echo '" class="form-control" ';
			echo 'name="' . $input_name . $name_suffix . '" ';
			echo 'id="' . $input_name . $name_suffix . '" ';
			echo 'value="';
			echo $config[$input_name];
			echo'"';

			echo isset($input['attr']['maxlength']) ? '' : ' maxlength="60"';
			echo isset($input['attr']['minlength']) ? '' : ' minlength="1"';
			echo isset($input['required']) ? ' required' : '';

			if (isset($input['attr']))
			{
				foreach ($input['attr'] as $attr_name => $attr_value)
				{
					echo ' ' . $attr_name . '="';
					echo strtr($attr_value, $attr_replace_ary);
					echo '"';
				}
			}

			echo '>';
		}

		if (isset($input['addon']) || isset($input['addon_fa']))
		{
			echo '</div>';
		}

		echo '</div>';
	}

	if (isset($input['max_inputs']) && $input['max_inputs'] > 1)
	{
		echo '<div class="form-group hidden add-input">';
		echo '<div class="extra-field">';
		echo '<br>';
		echo '<span class="btn btn-default"><i class="fa fa-plus" ></i> ';
		echo $input['add_btn_text'] ?? 'Extra';
		echo '</span>';
		echo '</div>';
		echo '</div>';
	}

	if (isset($input['explain']))
	{
		echo '<p>';
		echo strtr($input['explain'], $explain_replace_ary);
		echo '</p>';
	}

	echo '</li>';
}

echo '</ul>';

echo '<div class="panel-heading">';

echo '<input type="hidden" name="tab" value="' . $active_tab . '">';

echo '<input type="submit" class="btn btn-primary" ';
echo 'value="Aanpassen" name="' . $active_tab . '_submit">';

echo $app['form_token']->get_hidden_input();

echo '</div>';

echo '</div>';

echo '</form>';

echo '</div>';

include __DIR__ . '/include/footer.php';

function cancel(string $tab):void
{
	header('Location: ' . generate_url('config', ['tab' => $tab]));
	exit;
}

function get_sortable_items_str(
	array $input_ary,
	array $v_options,
	array $items,
	string $class
):string
{
	$out = '';

	foreach ($items as $a)
	{
		$options = $input_ary[$a];

		if (!count($options))
		{
			continue;
		}
		else if (count($options) === 1)
		{
			$lbl = reset($options);
			$option = key($options);
			$out .= '<li class="list-group-item ';
			$out .= $class;
			$out .= '" ';
			$out .= 'data-block="';
			$out .= $a;
			$out .= '" ';
			$out .= 'data-option="';
			$out .= $option;
			$out .= '" >';
			$out .= '<span class="lbl">';
			$out .= $lbl;
			$out .= '</span>';
			$out .= '</li>';

			continue;
		}

		if (isset($v_options[$a]))
		{
			$option = $v_options[$a];
			$lbl = $options[$option];
		}
		else
		{
			$lbl = reset($options);
			$option = key($options);
		}

		$out .= '<li class="list-group-item ';
		$out .= $class;
		$out .= '" ';
		$out .= 'data-block="';
		$out .= $a;
		$out .= '" ';
		$out .= 'data-option="';
		$out .= $option;
		$out .= '">';
		$out .= '<span class="lbl">';
		$out .= $lbl;
		$out .= '</span>';
		$out .= '&nbsp;&nbsp;';
		$out .= '<button type="button" class="btn btn-default ';
		$out .= 'dropdown-toggle" ';
		$out .= 'data-toggle="dropdown" aria-haspopup="true" ';
		$out .= 'aria-expanded="false">';
		$out .= ' <span class="caret"></span>';
		$out .= '</button>';
		$out .= '<ul class="dropdown-menu">';

		foreach ($options as $k => $lbl)
		{
			$out .= '<li><a href="#" data-o="';
			$out .= $k;
			$out .= '">';
			$out .= $lbl;
			$out .= '</a></li>';
		}

		$out .= '</ul></li>';
	}

	for($i = 0; $i < 5; $i++)
	{
		$out .= '<li class="list-group-item"></li>';
	}

	return $out;
}

function get_tag_ary(string $tag_name, string $line):array
{
	$return_ary = [];

	$open = cnst_config::TAG[$tag_name]['open'];
	$close = cnst_config::TAG[$tag_name]['close'];

	$start = 0;

	do
	{
		$start = strpos($line, $open, $start);

		if ($start === false)
		{
			return $return_ary;
		}

		$start += strlen($open);

		$end = strpos($line, $close, $start);

		if ($end === false)
		{
			return $return_ary;
		}

		$return_ary[] = substr($line, $start, $end - $start);

		$start = $end + strlen($close);
	}
	while ($start < strlen($line));

	return $return_ary;
}