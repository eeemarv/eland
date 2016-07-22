<?php

$rootpath = './';
$page_access = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$setting = (isset($_GET['edit'])) ? $_GET['edit'] : false;
$submit = (isset($_POST['zend'])) ? true : false;

$active_tab = 'balance';
$active_tab = isset($_GET['active_tab']) ? $_GET['active_tab'] : $active_tab;
$active_tab = isset($_POST['active_tab']) ? $_POST['active_tab'] : $active_tab;

$register_link = $base_url . '/register.php';
$register_link_explain = 'Het registratieformulier kan je terugvinden op <a href="' . $register_link;
$register_link_explain .= '">' . $register_link . '</a>. Plaats deze link op je website.';

$tab_panes = [

	'balance'		=> [
		'lbl'		=> 'Saldo',
		'inputs'	=> [
			'minlimit'	=> [
				'lbl'	=> 'Minimum limiet',
				'type'	=> 'number',
				'explain'	=> 'Standaardwaarde minimum limiet voor nieuwe gebruikers.',
			],
			'maxlimit'	=> [
				'lbl'	=> 'Maximum limiet',
				'type'	=> 'number',
				'explain'	=> 'Standaardwaarde maximum limiet voor nieuwe gebruikers.',
			],
			'balance_equilibrium'	=> [
				'lbl'	=> 'Het uitstapsaldo voor actieve leden. ',
				'type'	=> 'number',
				'explain' => 'Het saldo van leden met status uitstapper kan enkel bewegen in de richting van deze instelling.'
			],

		],
	],

	'messages'		=> [
		'lbl'		=> 'Vraag en aanbod',
		'inputs'	=> [

			'msgs_days_default'	=> [
				'lbl'	=> 'Standaard geldigheidsduur in aantal dagen', 
				'explain' => 'Bij aanmaak van nieuw vraag of aanbod wordt deze waarde standaard ingevuld in het formulier.',
				'type'	=> 'number',
				'attr'	=> ['min' => 1, 'max' => 365],
			],

			'li_1'	=> [
				'inline' => '%1$s Ruim vervallen vraag en aanbod op na %2$s dagen.',
				'inputs' => [
					'msgcleanupenabled'	=> [
						'type'	=> 'checkbox',
					],
					'msgexpcleanupdays'	=> [
						'type'	=> 'number',
						'attr'	=> ['min' => 1, 'max' => 365],
					],
				],
			],
			'li_2'	=> [
				'inline' => '%1$s Mail een notificatie naar de eigenaar van een vraag of aanbod bericht op het moment dat het vervalt.',
				'inputs'	=> [
					'msgexpwarnenabled'	=> [
						'type'	=> 'checkbox',
					],
				],
			],
			'li_3'	=> [
				'inline' => '%1$s Mail de admin een overzicht van vervallen vraag en aanbod elke %2$s dagen.',
				'inputs' => [
					'adminmsgexp'	=> [
						'type'	=> 'checkbox',
					],
					'adminmsgexpfreqdays' => [
						'type'	=> 'number',
						'attr'	=> ['min' => 1, 'max' => 365],
					],
				],
			],
		]
	],

	'systemname'	=> [
		'lbl'	=> 'Groepsnaam',
		'inputs' => [
			'systemname' => [
				'lbl'	=> 'Groepsnaam',
			],
			'systemtag' => [
				'lbl'	=> 'Tag (hoofding voor emails)',
				'attr'	=> ['maxlength' => 30],
			],
		],
	],

	'currency'		=> [
		'lbl'	=> 'LETS-Eenheid',
		'inputs'	=> [
			'currency'	=> [
				'lbl'	=> 'Naam van LETS-Eenheid (meervoud)',
			],
			'currencyratio'	=> [
				'lbl'	=> 'Aantal per uur',
				'attr'	=> ['max' => 240, 'min' => 1],
				'type'	=> 'number',
			],
		],
	],

	'mailaddresses'	=> [
		'lbl'		=> 'Mailadressen',
		'inputs'	=> [
			'admin'	=> [
				'lbl'	=> 'Algemeen admin/beheerder',
				'attr' => ['minlength' => 7],
			],
			'newsadmin'	=> [
				'lbl'	=> 'Nieuwsbeheerder',
				'attr'	=> ['minlength' => 7],
			],
			'support'	=> [
				'lbl'	=> 'Support / Helpdesk',
				'attr'	=> ['minlength' => 7],
			],
		]
	],

	'date_format'	=> [
		'lbl'	=> 'Datum- en tijdsweergave',
		'inputs'	=> [
			'date_format' => [
				'type'		=> 'select',
				'options'	=> $date_format->get_options(),
			],
		],
	],

	'saldomail'		=> [
		'lbl'	=> 'Overzichtsmail',
		'lbl_pane'	=> 'Overzichtsmail met recent vraag en aanbod',
		'inputs' => [
			'li_1'	=> [
				'inline' => 'Verstuur de overzichtsmail met recent vraag en aanbod om de %1$s dagen',
				'inputs' => [
					'saldofreqdays'	=> [
						'type'	=> 'number',
						'attr'	=> ['class' => 'sm-size', 'min' => 1, 'max' => 120],
					],
				],
				'explain' => 'Noot: Leden kunnen steeds ontvangst van de overzichtsmail aan- of afzetten in hun profielinstellingen.',
			],
		],
	],

	'registration'	=> [
		'lbl'	=> 'Inschrijven',
		'lbl_pane'	=> 'Inschrijvingsformulier',
		'inputs'	=> [
			'li_1'	=> [
				'inline' => '%1$s inschrijvingsformulier aan.',
				'inputs' => [
					'registration_en' => [
						'type' => 'checkbox',
					],
				],
				'explain' => $register_link_explain,
			],
			'registration_top_text' => [
				'lbl'	=> 'Tekst boven het inschrijvingsformulier',
				'type'	=> 'textarea',
				'rich_edit'	=> true,
				'explain' => 'Geschikt bijvoorbeeld om nadere uitleg bij de inschrijving te geven.',
			],
			'registration_bottom_text' => [
				'lbl'	=> 'Tekst onder het inschrijvingsformulier',
				'type'	=> 'textarea',
				'rich_edit'	=> true,
				'explain'	=> 'Geschikt bijvoorbeeld om privacybeleid toe te lichten.',
			],
			'registration_success_text'	=> [
				'lbl'	=> 'Tekst na succesvol indienen formulier.',
				'type'	=> 'textarea',
				'rich_edit'	=> true,
				'explain'	=> 'Deze tekst wordt enkel getoond wanneer hieronder geen url ingevuld is.',
			],
			'registration_success_url'	=> [
				'lbl'	=> 'Url naar pagina na succesvol indienen formulier.',
				'type'	=> 'url',
				'explain'	=> 'Voer de gebruiker meteen terug naar je website na succesvol indienen van het formulier.',
			],
		],
	],

	'forum'	=> [
		'lbl'	=> 'Forum',
		'inputs'	=> [
			'li_1'	=> [
				'inline' => '%1$s Forum aan.',
				'inputs' => [
					'forum_en'	=> [
						'type'	=> 'checkbox',
					],
				]
			]
		],
	],

	'newuserdays'	=> [
		'lbl'	=> 'Instappers',
		'inputs'	=> [
			'newuserdays'	=> [
				'lbl'	=> 'Aantal dagen dat een nieuw lid als instapper getoond wordt.',
				'type'	=> 'number',
				'attr'	=> ['min' => 0, 'max' => 365],
			],
		],
	],

	'users_self_edit'	=> [
		'lbl'	=> 'Leden rechten',
		'inputs'	=> [
			'li_1' => [
				'inline' => '%1$s Leden kunnen zelf hun gebruikersnaam aanpassen.',
				'inputs' => [
					'users_can_edit_username' => [
						'type'	=> 'checkbox',
					],
				],
			],
			'li_2' => [
				'inline' => '%1$s Leden kunnen zelf hun volledige naam aanpassen.',
				'inputs' => [
					'users_can_edit_fullname' => [
						'type'	=> 'checkbox',
					],
				],
			],
		],
	],

	'css'	=> [
		'lbl'	=> 'Stijl',
		'inputs' => [
			'css' => [
				'type' 	=> 'url',
				'lbl'	=> 'Url van extra stijlblad (css-bestand)',
			],
		],
	],

	'system'	=> [
		'lbl'		=> 'Systeem',
		'inputs'	=> [
			'li_1'	=> [
				'inline'	=> '%1$s Mail functionaliteit aan: het systeem verstuurt mails.',
				'inputs'	=> [
					'mailenabled'	=> [
						'type'	=> 'checkbox',
					],
				],
			],
			'li_2' => [
				'inline' => '%1$s Onderhoudsmodus: alleen admins kunnen inloggen.',
				'inputs' => [
					'maintenance'	=> [
						'type'	=> 'checkbox',
					],
				],
			],
		],
	],

];

$config = [];

foreach ($tab_panes as $pane)
{
	if (isset($pane['inputs']))
	{
		foreach ($pane['inputs'] as $name => $input)
		{
			if (isset($input['inputs']))
			{
				foreach ($input['inputs'] as $sub_name => $sub_input)
				{
					$config[$sub_name] = readconfigfromdb($sub_name);
				}

				continue;
			}

			$config[$name] = readconfigfromdb($name);
		}
	}
}

if ($post)
{
	if (!isset($_POST[$active_tab . '_submit']))
	{
		$errors[] = 'Form submit error';
	}

	if ($error_token = get_error_form_token())
	{
		$errors[] = $error_token;
	}

	$posted_configs = $validators = [];

	foreach ($tab_panes[$active_tab]['inputs'] as $name => $input)
	{
		if (isset($input['inputs']))
		{
			foreach ($input['inputs'] as $sub_name => $sub_input)
			{
				$posted_configs[$sub_name] = trim($_POST[$sub_name]);

				$validators[$sub_name]['type'] = isset($sub_input['type']) ? $sub_input['type'] : 'text';
				$validators[$sub_name]['attr'] = isset($sub_input['attr']) ? $sub_input['attr'] : [];
				$validators[$sub_name]['required'] = isset($sub_input['required']) ? true : false;
			}

			continue;
		}

		$posted_configs[$name] = trim($_POST[$name]);

		$validators[$name]['type'] = isset($input['type']) ? $input['type'] : 'text';
		$validators[$name]['attr'] = isset($input['attr']) ? $input['attr'] : [];
		$validators[$name]['required'] = isset($input['required']) ? true : false;
	}

	foreach ($posted_configs as $name => $value)
	{
		if ($value === $config[$name])
		{
			unset($posted_configs[$name]);
			continue;
		}

		if ($name == 'date_format')
		{
			$error = $date_format->get_error($value);

			if ($error)
			{
				$errors[] = $error;
			}

			continue;
		}

		$validator = $validators[$name];

		if ($validator['type'] == 'text')
		{
			if (isset($validator['attr']['maxlength']) && strlen($value) > $validator['attr']['maxlength'])
			{
				$errors[] = 'Fout: de waarde mag maximaal ' . $validators['attr']['maxlength'] . ' tekens lang zijn.';
			}

			if (isset($validator['attr']['minlength']) && strlen($value) < $validator['attr']['minlength'])
			{
				$errors[] = 'Fout: de waarde moet minimaal ' . $validators['attr']['minlength'] . ' tekens lang zijn.';
			}

			continue;
		}

		if ($validator['type'] == 'number')
		{
			if (!ctype_digit($value))
			{
				$errors[] = 'Fout: de waarde moet een getal zijn.';
			}

			if (isset($validator['attr']['max']) && $value > $validator['attr']['max'])
			{
				$errors[] = 'Fout: de waarde mag maximaal ' . $validators['attr']['max'] . ' bedragen.';
			}

			if (isset($validator['attr']['min']) && $value < $validator['attr']['min'])
			{
				$errors[] = 'Fout: de waarde moet minimaal ' . $validators['attr']['min'] . ' bedragen.';
			}

			continue;
		}

		if ($validator['type'] == 'checkbox')
		{
			$posted_configs[$name] = ($value) ? '1' : '0';

			continue;
		}

		if ($validator['type'] == 'email')
		{
			if (!filter_var($value, FILTER_VALIDATE_EMAIL))
			{
				$errors[] =  $value . ' is geen geldig email adres.';
			}

			continue;
		}

		if ($validator['type'] == 'url')
		{
			if (!filter_var($value, FILTER_VALIDATE_URL))
			{
				$errors[] =  $value . ' is geen geldig email adres.';
			}

			continue;
		}

		if ($validator['type'] == 'textarea')
		{
			$value = (strip_tags($value)) ? $value : '';

			$posted_configs[$name] = $value;

			if (isset($validator['attr']['maxlength']) && strlen($value) > $validator['attr']['maxlength'])
			{
				$errors[] = 'Fout: de waarde mag maximaal ' . $validators['attr']['maxlength'] . ' tekens lang zijn.';
			}

			if (isset($validator['attr']['minlength']) && strlen($value) < $validator['attr']['minlength'])
			{
				$errors[] = 'Fout: de waarde moet minimaal ' . $validators['attr']['minlength'] . ' tekens lang zijn.';
			}
		}
	}

	if (!count($posted_configs))
	{
		$alert->warning('Geen gewijzigde waarden.');
		cancel();
	}

	if (count($errors))
	{
		$alert->error($errors);
		cancel();
	}

	foreach ($posted_configs as $name => $value)
	{
		$exdb->set('setting', $name, ['value' => $value]);

		$redis->del($schema . '_config_' . $name);

		// check existance of config in eLAS first to prevent string too long error

		if ($db->fetchColumn('select setting from config where setting = ?', [$name]))
		{
			$db->update('config', ['value' => $value, '"default"' => 'f'], ['setting' => $name]);
		}
	}

	if (count($posted_configs) > 1)
	{
		$alert->success('De instellingen zijn aangepast.');
	}
	else
	{
		$alert->success('De instelling is aangepast.');
	}

	cancel();
}


/*

if ($setting)
{
	$eh_config = isset($eland_config_default[$setting]) ? $eland_config_default[$setting] : false;

	if ($submit)
	{
		$value = $_POST['value'];

		if (strlen($value) > 60)
		{
			$errors[] = 'De waarde mag maximaal 60 tekens lang zijn.';
		}

		if ($value == '')
		{
			$errors[] = 'De waarde mag niet leeg zijn.';
		}

		if ($error_token = get_error_form_token())
		{
			$errors[] = $error_token;
		}

		if (!count($errors))
		{
			$exdb->set('setting', $setting, ['value' => $value]);

			if (!$eland_config[$setting])			
			{
				if (!$db->update('config', array('value' => $value, '"default"' => 'f'), array('setting' => $setting)))
				{
					return false;
				}
			}

			$redis_key = $schema . '_config_' . $setting;
			$redis->set($redis_key, $value);
			$redis->expire($redis_key, 2592000);

			$alert->success('Instelling aangepast.');
			cancel();
		}

		$alert->error($errors);
	}
	else
	{
		$value = readconfigfromdb($setting);
	}

	$description = ($eland_config_explain[$setting]) ? $eland_config_explain[$setting] : $db->fetchColumn('select description from config where setting = ?', array($setting));

	$h1 = 'Instelling ' . $setting . ' aanpassen';
	$fa = 'gears';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<p>' . $description . '</p>';

	echo '<div class="form-group">';
	echo '<label for="setting" class="col-sm-2 control-label">Instelling</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="setting" name="setting" ';
	echo 'value="' . $setting . '" required readonly>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="value" class="col-sm-2 control-label">Waarde</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="value" name="value" ';
	echo 'value="' . $value . '" required maxlength="60">';
	echo '</div>';
	echo '</div>';

	echo aphp('config', [], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-primary">';
	generate_form_token();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

// exclude plaza stuff, emptypasswordlogin, share_enabled, pwscore

$configi = $db->fetchAll('select *
	from config
	where category not like \'plaza%\'
		and setting <> \'emptypasswordlogin\'
		and setting <> \'share_enabled\'
		and setting <> \'pwscore\'
		and setting <> \'msgexpwarningdays\'
		and setting <> \'news_announce\'
		and setting <> \'mailinglists_enabled\'
		and setting <> \'from_address\'
		and setting <> \'from_address_transactions\'
		and setting <> \'ets_enabled\'
	order by category, setting');

foreach ($configi as $c)
{
	$configi[$c['setting']] = $c['value'];
}



foreach ($eland_config as $setting => $default)
{
	unset($value);

	$row = $exdb->get('setting', $setting);

	if ($row)
	{
		$value = $row['data']['value'];
	}

	$config[] = array(
		'category'		=> 'eLAND',
		'setting'		=> $setting,
		'value'			=> isset($value) ? $value : $default[0],
		'description'	=> $default[1],
		'default'		=> isset($value) ? false : true,
	);

	$config[$setting] = $value;
}

*/


$include_ary[] = 'summernote';
$include_ary[] = 'config.js';

$h1 = 'Instellingen';
$fa = 'gears';

include $rootpath . 'includes/inc_header.php';

echo '<div>';
echo '<ul class="nav nav-pills" role="tablist">';

foreach ($tab_panes as $id => $pane)
{	
	echo '<li role="presentation"';
	echo ($id == $active_tab) ? ' class="active"' : '';
	echo '>';
	echo '<a href="#' . $id . '" aria-controls="' . $id . '" role="tab" data-toggle="tab">';
	echo $pane['lbl'];
	echo '</a>';
	echo '</li>';
}

echo '</ul>';

echo '<div class="tab-content">';

///

foreach ($tab_panes as $id => $pane)
{
	$active = ($id == $active_tab) ? ' active' : '';

	echo '<div role="tabpanel" class="tab-pane' . $active . '" id="' . $id . '">';

	echo '<form method="post" class="form form-horizontal">';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading"><h4>';
	echo (isset($pane['lbl_pane'])) ? $pane['lbl_pane'] : $pane['lbl'];
	echo '</h4></div>';

	echo '<ul class="list-group">';

	foreach ($pane['inputs'] as $name => $input)
	{
		echo '<li class="list-group-item">';

		if (isset($input['inline']))
		{
			$input_ary = [];

			if (isset($input['inputs']))
			{
				foreach ($input['inputs'] as $inline_name => $inline_input)
				{
					$str = '<input type="';
					$str .= isset($inline_input['type']) ? $inline_input['type'] : 'text';
					$str .= '" name="' . $inline_name . '"';

					if ($inline_input['type'] == 'checkbox')
					{
						$str .= ' value="1"';
						$str .= $config[$inline_name] ? ' checked="checked"' : '';
					}
					else
					{
						$str .= ' class="sm-size"';
						$str .= ' value="' . $config[$inline_name] . '"';
					}

					if (isset($inline_input['attr']))
					{
						foreach ($inline_input['attr'] as $attr_name => $attr_value)
						{
							$str .= ' ' . $attr_name . '="' . $attr_value . '"';
						}
					}

					$str .= '>';

					$input_ary[] = $str;
				}
			}

			echo '<p>' . vsprintf($input['inline'], $input_ary) . '</p>';
		}
		else
		{
			echo '<div class="form-group">';

			if (isset($input['lbl']))
			{
				echo '<label class="col-sm-3 control-label">';
				echo $input['lbl'];
				echo '</label>';
				echo '<div class="col-sm-9">';
			}
			else
			{
				echo '<div class="col-sm-12">';
			}

			if (isset($input['type']) && $input['type'] == 'select')
			{
				echo '<select class="form-control" name="' . $name . '">';

				render_select_options($input['options'], $config[$name]);

				echo '</select>';
			}
			else if (isset($input['type']) && $input['type'] == 'textarea')
			{
				echo '<textarea name="' . $name . '" id="' . $name . '" class="form-control';
				echo isset($input['rich_edit']) ? ' rich-edit' : '';
				echo '" rows="4"';

				echo (isset($input['attr']['maxlength'])) ? '' : ' maxlength="2000"';
				echo (isset($input['attr']['minlength'])) ? '' : ' minlength="1"';

				if (isset($input['attr']))
				{
					foreach ($input['attr'] as $attr_name => $attr_value)
					{
						echo ' ' . $attr_name . '="' . $attr_value . '"';
					}
				}

				echo '>';
				echo $config[$name];
				echo '</textarea>';
			}
			else
			{
				echo '<input type="';
				echo (isset($input['type'])) ? $input['type'] : 'text';
				echo '" class="form-control" ';
				echo 'name="' . $name . '" value="' . $config[$name] . '"';

				echo (isset($input['attr']['maxlength'])) ? '' : ' maxlength="60"';
				echo (isset($input['attr']['minlength'])) ? '' : ' minlength="1"';

				if (isset($input['attr']))
				{
					foreach ($input['attr'] as $attr_name => $attr_value)
					{
						echo ' ' . $attr_name . '="' . $attr_value . '"';
					}
				}

				echo '>';
			}

			echo '</div>';
			echo '</div>';
		}

		if (isset($input['explain']))
		{
			echo '<p><small>' . $input['explain'] . '</small></p>';
		}

		echo '</li>';
	}

	echo '</ul>';

	echo '<div class="panel-footer">';

	echo '<input type="hidden" name="active_tab" value="' . $id . '">';
	echo '<input type="submit" class="btn btn-primary" value="Aanpassen" name="' . $id . '_submit">';
	generate_form_token();

	echo '</div>';

	echo '</div>';

	echo '</form>';

	echo '</div>';
}

echo '</div>';
echo '</div>';


include $rootpath . 'includes/inc_footer.php';

function cancel()
{
	global $active_tab;

	header('Location: ' . generate_url('config', ['active_tab' => $active_tab]));
	exit;
}
