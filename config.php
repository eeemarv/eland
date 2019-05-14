<?php

$page_access = 'admin';
require_once __DIR__ . '/include/web.php';

use util\cnst_config;

$setting = $_GET['edit'] ?? false;
$submit = isset($_POST['zend']) ? true : false;

$active_tab = 'balance';
$active_tab = $_GET['active_tab'] ?? $active_tab;
$active_tab = $_POST['active_tab'] ?? $active_tab;

$block_ary = [
	'periodic_mail'	=> cnst_config::PERIODIC_MAIL_BLOCK_ARY,
];

$cond_ary = [
	'config_template_lets'	=> true,
];

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

foreach (cnst_config::TAB_PANES[$active_tab]['inputs'] as $input_name => $input_config)
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

	$posted_configs = $validators = $post_actions = [];

	foreach (cnst_config::TAB_PANES[$active_tab]['inputs'] as $name => $input)
	{
		if (isset($input['cond']) &&
			!isset($cond_ary[$input['cond']]))
		{
			continue;
		}

		if (isset($input['inputs']))
		{
			foreach ($input['inputs'] as $sub_name => $sub_input)
			{
				$posted_configs[$sub_name] = trim($_POST[$sub_name]);

				$validators[$sub_name]['type'] = $sub_input['type'] ?? 'text';
				$validators[$sub_name]['attr'] = $sub_input['attr'] ?? [];
				$validators[$sub_name]['required'] = isset($sub_input['required']) ? true : false;
				$validators[$sub_name]['max_inputs'] = $sub_input['max_inputs'] ?? 1;

				$post_actions[$sub_name] = $sub_input['post_action'] ?? [];
			}

			continue;
		}

		$posted_configs[$name] = trim($_POST[$name]);

		$validators[$name]['type'] = $input['type'] ?? 'text';
		$validators[$name]['attr'] = $input['attr'] ?? [];
		$validators[$name]['required'] = isset($input['required']) ? true : false;
		$validators[$name]['max_inputs'] = $input['max_inputs'] ?? 1;

		$post_actions[$name] = $input['post_action'] ?? [];
	}

	foreach ($posted_configs as $name => $value)
	{
		$validator = $validators[$name];

		$err_n = ' (' . $name . ')';

		if ($validator['required'] && $value === '')
		{
			$errors[] = 'Het veld is verplicht in te vullen.' . $err_n;
			continue;
		}

		if ($validator['type'] == 'text' || $validator['type'] == 'textarea')
		{
			$config_htmlpurifier = HTMLPurifier_Config::createDefault();
			$config_htmlpurifier->set('Cache.DefinitionImpl', null);
			$htmlpurifier = new HTMLPurifier($config_htmlpurifier);
			$value = $htmlpurifier->purify($value);
		}

		$value = (strip_tags($value) !== '') ? $value : '';

		if ($validator['type'] === 'checkbox')
		{
			$value = $value ? '1' : '0';
		}

		if ($value === $config[$name])
		{
			unset($posted_configs[$name]);
			continue;
		}

		if ($name == 'date_format')
		{
			$error = $app['date_format']->get_error($value);

			if ($error)
			{
				$errors[] = $error . $err_n;
			}

			continue;
		}

		if ($validator['type'] === 'text')
		{
			$posted_configs[$name] = $value;

			if (isset($validator['attr']['maxlength'])
				&& strlen($value) > $validator['attr']['maxlength'])
			{
				$errors[] = 'Fout: de waarde mag maximaal ' .
					$validator['attr']['maxlength'] .
					' tekens lang zijn.' . $err_n;
			}

			if (isset($validator['attr']['minlength'])
				&& strlen($value) < $validator['attr']['minlength'])
			{
				$errors[] = 'Fout: de waarde moet minimaal ' .
					$validator['attr']['minlength'] .
					' tekens lang zijn.' . $err_n;
			}

			continue;
		}

		if ($validator['type'] === 'number')
		{
			if ($value === '' && !$validator['required'])
			{
				continue;
			}

			if (!filter_var($value, FILTER_VALIDATE_INT))
			{
				$errors[] = 'Fout: de waarde moet een getal zijn.' . $err_n;
			}

			if (isset($validator['attr']['max'])
				&& $value > $validator['attr']['max'])
			{
				$errors[] = 'Fout: de waarde mag maximaal ' .
					$validator['attr']['max'] .
					' bedragen.' . $err_n;
			}

			if (isset($validator['attr']['min'])
				&& $value < $validator['attr']['min'])
			{
				$errors[] = 'Fout: de waarde moet minimaal ' .
					$validator['attr']['min'] .
					' bedragen.' . $err_n;
			}

			continue;
		}

		if ($validator['type'] === 'checkbox')
		{
			$posted_configs[$name] = $value;

			continue;
		}

		if ($validator['type'] === 'email')
		{
			if (isset($validator['max_inputs']))
			{
				$mail_ary = explode(',', $value);

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

			if (!filter_var($value, FILTER_VALIDATE_EMAIL))
			{
				$errors[] =  $value . ' is geen geldig E-mail adres.' . $err_n;
			}

			continue;
		}

		if ($validator['type'] === 'url')
		{
			if ($value != '')
			{
				if (!filter_var($value, FILTER_VALIDATE_URL))
				{
					$errors[] =  $value . ' is geen geldig url adres.' . $err_n;
				}
			}

			continue;
		}

		if ($validator['type'] === 'textarea')
		{
			$posted_configs[$name] = $value;

			if (isset($validator['attr']['maxlength'])
				&& strlen($value) > $validator['attr']['maxlength'])
			{
				$errors[] = 'Fout: de waarde mag maximaal ' .
					$validator['attr']['maxlength'] .
					' tekens lang zijn.' . $err_n;
			}

			if (isset($validator['attr']['minlength'])
				&& strlen($value) < $validator['attr']['minlength'])
			{
				$errors[] = 'Fout: de waarde moet minimaal ' .
					$validator['attr']['minlength'] .
					' tekens lang zijn.' . $err_n;
			}
		}

		if ($validator['type'] === 'sortable')
		{

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

	foreach ($posted_configs as $name => $value)
	{
		$app['config']->set($name, $app['tschema'], $value);

		// prevent string too long error for eLAS database

		if ($validators[$name]['max_inputs'] > 1)
		{
			[$value] = explode(',', $value);
			$value = trim($value);
		}

		$value = substr($value, 0, 60);

		$app['db']->update($app['tschema'] . '.config',
			['value' => $value, '"default"' => 'f'],
			['setting' => $name]);

		$p_acts = is_array($post_actions[$name]) ? $post_actions[$name] : [$post_actions[$name]];

		foreach($p_acts as $p_act)
		{
			$execute_post_actions[$p_act] = true;
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
echo '<ul class="nav nav-pills" role="tablist">';

foreach (cnst_config::TAB_PANES as $tab_id => $pane)
{
	echo '<li role="presentation"';
	echo $tab_id === $active_tab ? ' class="active"' : '';
	echo '>';
	echo aphp('config',
		['active_tab' => $tab_id],
		$pane['lbl'], false, false, false,
		['role'	=> 'tab']);
	echo '</li>';
}

echo '</ul>';

echo '<div class="tab-content">';

$pane = cnst_config::TAB_PANES[$active_tab];

echo '<div role="tabpanel" ';
echo 'class="tab-pane active" ';
echo 'id="';
echo $active_tab;
echo '">';

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
	}

	if (isset($input['cond']) && !$input['cond'])
	{
		continue;
	}

	echo '<li class="list-group-item bg-info">';

	if (isset($input['max_inputs']) && $input['max_inputs'] > 1)
	{
		echo '<input type="hidden" value="';
		echo $config[$name];
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

		foreach ($inline_input_names as $inline_name)
		{
			error_log($inline_name);

			$inline_input = cnst_config::INPUTS[$inline_name];

			$str = '<input type="';
			$str .= $inline_input['type'] ?? 'text';
			$str .= '" name="';
			$str .= $inline_name;
			$str .= '"';

			if (!$id_for_label)
			{
				$id_for_label = 'inline_id_' . $inline_name;
				$str .= ' id="' . $id_for_label . '"';
			}

			if ($inline_input['type'] == 'checkbox')
			{
				$str .= ' value="1"';
				$str .= $config[$inline_name] ? ' checked="checked"' : '';
			}
			else
			{
				$str .= ' class="sm-size"';
				$str .= ' value="';
				$str .= $config[$inline_name];
				$str .= '"';
			}

			if (isset($inline_input['attr']))
			{
				foreach ($inline_input['attr'] as $attr_name => $attr_value)
				{
					$str .= ' ';
					$str .= $attr_name;
					$str .= '="';
					$str .=  strtr($attr_value, $attr_replace_ary);
					$str .= '"';
				}
			}

			$str .= isset($inline_input['required']) ? ' required' : '';

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

		error_log(json_encode($replace_inline_ary));

		echo strtr($input['inline'], $replace_inline_ary);

		if ($id_for_label)
		{
			echo '</label>';
		}

		echo '</p>';
	}
	else if (isset($input['type']) && $input['type'] === 'sortable')
	{
		$v_options = $active = $inactive = [];
		$value_ary = explode(',', ltrim($config[$input_name], '+ '));

		foreach ($value_ary as $v)
		{
			[$block, $option] = explode('.', $v);
			$v_options[$block] = $option;
			$active[] = $block;
		}

		foreach ($input['ary'] as $block => $options)
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
			$input['ary'],
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
			$input['ary'],
			$v_options,
			$inactive,
			'bg-danger');

		echo '</ul';
		echo '</div>';
		echo '</div>';
		echo '</div>'; // col

		echo '</div>'; // row

		echo '<input type="hidden" name="' . $input_name . '" ';
		echo 'value="' . $config[$input_name] . '" id="' . $input_name . '">';
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
					echo strtr($attr_ary, $attr_replace_ary);
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
			echo 'value="' . $config[$input_name] . '"';

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

echo '<input type="hidden" name="active_tab" value="' . $active_tab . '">';

echo '<input type="submit" class="btn btn-primary" ';
echo 'value="Aanpassen" name="' . $active_tab . '_submit">';

echo $app['form_token']->get_hidden_input();

echo '</div>';

echo '</div>';

echo '</form>';

echo '</div>';


echo '</div>';
echo '</div>';

include __DIR__ . '/include/footer.php';

function cancel(string $active_tab):void
{
	header('Location: ' . generate_url('config', ['active_tab' => $active_tab]));
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