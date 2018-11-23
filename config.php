<?php

$page_access = 'admin';
require_once __DIR__ . '/include/web.php';

$setting = $_GET['edit'] ?? false;
$submit = isset($_POST['zend']) ? true : false;

$active_tab = 'balance';
$active_tab = $_GET['active_tab'] ?? $active_tab;
$active_tab = $_POST['active_tab'] ?? $active_tab;

$register_link = $app['base_url'] . '/register.php';
$register_link_explain = 'Het registratieformulier kan je terugvinden op <a href="' . $register_link;
$register_link_explain .= '">' . $register_link . '</a>. Plaats deze link op je website.';
$register_link_explain .= '<br>Bij inschrijving wordt een nieuwe gebruiker zonder code aangemaakt met status info-pakket.';
$register_link_explain .= '<br>De admin krijgt een notificatie-email bij elke inschrijving.';

$register_success_explain = 'Hier kan je aan de gebruiker uitleggen wat er verder gaat gebeuren. <br>';
$register_success_explain .= 'Als je Systeem een website heeft, is het nuttig om een link op te nemen ';
$register_success_explain .= 'om de gebruiker terug te voeren.';

$contact_link = $app['base_url'] . '/contact.php';
$contact_link_explain = 'Het contactformulier kan je terugvinden op <a href="' . $contact_link;
$contact_link_explain .= '">' . $contact_link . '</a>.';

$map_template_vars = [
	'voornaam' 			=> 'first_name',
	'achternaam'		=> 'last_name',
	'postcode'			=> 'postcode',
];

$periodic_mail_item_show_options = $periodic_mail_item_show_options_not_all = [
	'all'		=> 'Alle',
	'recent'	=> 'Recente',
	'none'		=> 'Geen',
];

$landing_page_options = [
	'messages'		=> 'Vraag en aanbod',
	'users'			=> 'Leden',
	'transactions'	=> 'Transacties',
	'news'			=> 'Nieuws',
];

unset($periodic_mail_item_show_options_not_all['all']);

$periodic_mail_block_ary = [
	'messages'		=> [
		'recent'	=> 'Recent vraag en aanbod',
	],
	'interlets'		=> [
		'recent'	=> 'Recent interSysteem vraag en aanbod',
	],
	'forum'			=> [
		'recent'	=> 'Recente forumberichten',
	],
	'news'			=> [
		'all'		=> 'Alle nieuwsberichten',
		'recent'	=> 'Recente nieuwsberichten',
	],
	'docs'			=> [
		'recent'	=> 'Recente documenten',
	],
	'new_users'		=> [
		'all'		=> 'Alle nieuwe leden',
		'recent'	=> 'Recente nieuwe leden',
	],
	'leaving_users'	=> [
		'all'		=> 'Alle uitstappende leden',
		'recent'	=> 'Recent uitstappende leden',
	],
	'transactions' => [
		'recent'	=> 'Recente transacties',
	],
];

if (!$app['config']->get('forum_en'))
{
	unset($periodic_mail_block_ary['forum']);
}

if (!$app['config']->get('interlets_en') || !$app['config']->get('template_lets'))
{
	unset($periodic_mail_block_ary['interlets']);
}

$currency = $app['config']->get('currency');

$tab_panes = [

	'balance'		=> [
		'lbl'		=> 'Saldo',
		'inputs'	=> [
			'minlimit'	=> [
				'addon'	=> $currency,
				'lbl'	=> 'Minimum Systeemslimiet',
				'type'	=> 'number',
				'explain'	=> 'Minimum Limiet die geldt voor alle Accounts, behalve voor die Accounts waarbij een Minimum Account Limiet ingesteld is. Kan leeg gelaten worden.',
			],
			'maxlimit'	=> [
				'addon'	=> $currency,
				'lbl'	=> 'Maximum Systeemslimiet',
				'type'	=> 'number',
				'explain'	=> 'Maximum Limiet die geldt voor alle Accounts, behalve voor die Accounts waarbij een Maximum Account Limiet ingesteld is. Kan leeg gelaten worden.',
			],
			'preset_minlimit'	=> [
				'addon'	=> $currency,
				'lbl'	=> 'Preset Minimum Account Limiet',
				'type'	=> 'number',
				'explain'	=> 'Bij aanmaak van een nieuw Account wordt deze Minimum Account Limiet vooraf ingevuld in het aanmaakformulier. Dit heeft enkel zin wanneer instappende leden een afwijkende Minimum Account Limiet hebben van de Minimum Systeemslimiet. Deze instelling is ook nuttig wanneer de Automatische Minimum Limiet gebruikt wordt. Dit veld kan leeg gelaten worden.',
			],
			'preset_maxlimit'	=> [
				'addon'	=> $currency,
				'lbl'	=> 'Preset Maximum Account Limiet',
				'type'	=> 'number',
				'explain'	=> 'Bij aanmaak van een nieuw Account wordt deze Maximum Account Limiet vooraf ingevuld in het aanmaakformulier. Dit heeft enkel zin wanneer instappende leden een afwijkende Maximum Account Limiet hebben van de Maximum Systeemslimiet. Dit veld kan leeg gelaten worden.',

			],
			'balance_equilibrium'	=> [
				'addon'		=> $currency,
				'lbl'		=> 'Het uitstapsaldo voor actieve leden. ',
				'type'		=> 'number',
				'required'	=> true,
				'explain' 	=> 'Het saldo van leden met status uitstapper kan enkel bewegen in de richting van deze instelling.'
			],

		],
	],

	'messages'		=> [
		'lbl'		=> 'Vraag en aanbod',
		'inputs'	=> [

			'msgs_days_default'	=> [
				'addon'	=> 'dagen',
				'lbl'	=> 'Standaard geldigheidsduur',
				'explain' => 'Bij aanmaak van nieuw vraag of aanbod wordt deze waarde standaard ingevuld in het formulier.',
				'type'	=> 'number',
				'attr'	=> ['min' => 1, 'max' => 1460],
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
		],
	],

	'systemname'	=> [
		'lbl'	=> 'Systeemsnaam',
		'inputs' => [
			'systemname' => [
				'lbl'		=> 'Systeemsnaam',
				'required'	=> true,
			],
			'systemtag' => [
				'lbl'		=> 'Tag',
				'explain'	=> 'Prefix tussen haken [tag] in onderwerp van alle E-mail-berichten',
				'required'	=> true,
				'attr'		=> ['maxlength' => 30],
			],
		],
	],

	'currency'		=> [
		'lbl'	=> 'Munteenheid',
		'inputs'	=> [
			'currency'	=> [
				'lbl'		=> 'Naam van Munt (meervoud)',
				'required'	=> true,
			],

			'currencyratio'	=> [
				'cond'		=> $app['config']->get('template_lets') ? true : false,
				'lbl'		=> 'Aantal per uur',
				'attr'		=> ['max' => 240, 'min' => 1],
				'type'		=> 'number',
				'explain'	=> 'Deze instelling heeft enkel betrekking op Tijdsbanken. Zij is vereist voor eLAS/eLAND interSysteem-verbindingen zodat zij een gemeenschappelijke tijdsbasis kunnen hebben.',
			],
		],
	],

	'mailaddresses'	=> [
		'lbl'		=> 'E-Mail Adressen',
		'inputs'	=> [
			'admin'	=> [
				'lbl'	=> 'Algemeen admin/beheerder',
				'attr' 	=> ['minlength' => 7],
				'type'	=> 'email',
				'max_inputs'	=> 5,
				'add_btn_text' => 'Extra E-mail Adres',
			],
			'newsadmin'	=> [
				'lbl'	=> 'Nieuwsbeheerder',
				'attr'	=> ['minlength' => 7],
				'type'	=> 'email',
				'max_inputs'	=> 5,
				'add_btn_text'	=> 'Extra E-mail Adres',
			],
			'support'	=> [
				'lbl'	=> 'Support / Helpdesk',
				'attr'	=> ['minlength' => 7],
				'type'	=> 'email',
				'max_inputs'	=> 5,
				'add_btn_text'	=> 'Extra E-mail Adres',
			],
		]
	],

	'saldomail'		=> [
		'lbl'	=> 'Overzichts E-mail',
		'lbl_pane'	=> 'Periodieke Overzichts E-mail',
		'inputs' => [
			'li_1'	=> [
				'inline' => 'Verstuur de Periodieke Overzichts E-mail om de %1$s dagen',
				'inputs' => [
					'saldofreqdays'	=> [
						'type'		=> 'number',
						'attr'		=> ['class' => 'sm-size', 'min' => 1, 'max' => 120],
						'required'	=> true,
					],
				],
				'explain' => 'Noot: Leden kunnen steeds ontvangst van de Periodieke Overzichts E-mail aan- of afzetten in hun profielinstellingen.',
			],

			'periodic_mail_block_ary' => [
				'lbl'				=> 'E-mail opmaak (versleep blokken)',
				'type'				=> 'sortable',
				'explain_top'		=> 'Verslepen gaat met
					muis of touchpad, maar misschien niet met touch-screen.
					"Recent" betekent "sinds
					de laatste periodieke overzichtsmail".',
				'lbl_active' 		=> 'Inhoud',
				'lbl_inactive'		=> 'Niet gebruikte blokken',
				'ary'				=> $periodic_mail_block_ary,
			],
		],
	],

	'contact'	=> [
		'lbl'	=> 'Contact',
		'lbl_pane'	=> 'Contact Formulier',
		'inputs'	=> [
			'li_1'	=> [
				'inline' => '%1$s contact formulier aan.',
				'inputs' => [
					'contact_form_en' => [
						'type' => 'checkbox',
					],
				],
				'explain' => $contact_link_explain,
			],
			'contact_form_top_text' => [
				'lbl'	=> 'Tekst boven het contact formulier',
				'type'	=> 'textarea',
				'rich_edit'	=> true,
			],
			'contact_form_bottom_text' => [
				'lbl'		=> 'Tekst onder het contact formulier',
				'type'		=> 'textarea',
				'rich_edit'	=> true,
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
				'lbl'		=> 'Tekst onder het inschrijvingsformulier',
				'type'		=> 'textarea',
				'rich_edit'	=> true,
				'explain'	=> 'Geschikt bijvoorbeeld om privacybeleid toe te lichten.',
			],

			'registration_success_text'	=> [
				'lbl'	=> 'Tekst na succesvol indienen formulier.',
				'type'	=> 'textarea',
				'rich_edit'	=> true,
				'explain'	=> $register_success_explain,
			],

			'registration_success_mail'	=> [
				'lbl'		=> 'Verstuur E-mail naar gebruiker bij succesvol indienen formulier',
				'type'		=> 'textarea',
				'rich_edit'	=> true,
				'attr'		=> ['data-template-vars' => implode(',', array_keys($map_template_vars))],
			],
		],
	],

	'news'	=> [
		'lbl'	=> 'Nieuws',
		'inputs'	=> [
			'li_1'	=> [
				'inline' => '%1$s Sorteer nieuwsberichten chronologisch op agendadatum.',
				'inputs' => [
					'news_order_asc'	=> [
						'type'	=> 'checkbox',
					],
				]
			]
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

	'users'	=> [
		'lbl'	=> 'Leden',
		'inputs'	=> [
			'newuserdays' => [
				'addon'		=> 'dagen',
				'lbl'		=> 'Periode dat een nieuw lid als instapper getoond wordt.',
				'type'		=> 'number',
				'attr'		=> ['min' => 0, 'max' => 365],
				'required'	=> true,
			],

			'li_2' => [
				'inline' => '%1$s Leden kunnen zelf de Gebruikersnaam aanpassen.',
				'inputs' => [
					'users_can_edit_username' => [
						'type'	=> 'checkbox',
					],
				],
			],

			'li_3' => [
				'inline' => '%1$s Leden kunnen zelf het veld Volledige Naam aanpassen.',
				'inputs' => [
					'users_can_edit_fullname' => [
						'type'	=> 'checkbox',
					],
				],
			],
		],
	],

	'system'	=> [
		'lbl'		=> 'Systeem',
		'inputs'	=> [

			'li_1'	=> [
				'inline'	=> '%1$s E-mail functionaliteit aan: het systeem verstuurt E-mails.',
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

			'li_3' => [
				'inline'	=> '%1$s Dit Systeem is een Tijdsbank (munt met tijdsbasis).',
				'inputs'	=> [
					'template_lets'	=> [
						'type'	=> 'checkbox',
						'post_action'	=> 'clear_eland_interlets_cache',
					],
				],
			],

			'li_4'	=> [
				'inline'	=> '%1$s Gebruik eLAS/eLAND interSysteem. Deze instelling is enkel geldig wanneer hierboven
					"Tijdsbank" geselecteerd is. eLAS/eLAND interSysteem is enkel mogelijk met
					munten met gemeenschappelijke tijdsbasis.',
				'inputs'	=> [
					'interlets_en'	=> [
						'type'	=> 'checkbox',
						'post_action'	=> 'clear_eland_interlets_cache',
					],
				],
			],

			'default_landing_page'	=> [
				'lbl'		=> 'Standaard landingspagina',
				'type'		=> 'select',
				'options'	=> $landing_page_options,
				'required'	=> true,
			],

			'homepage_url'	=> [
				'lbl'		=> 'Website url',
				'type'		=> 'url',
				'explain'	=> 'Titel en logo in de navigatiebalk linken naar deze url.',
			],

			'date_format'	=> [
				'lbl'		=> 'Datum- en tijdsweergave',
				'type'		=> 'select',
				'options'	=> $app['date_format']->get_options(),
			],

			'css'	=> [
				'lbl'		=> 'Stijl (css)',
				'type' 		=> 'url',
				'explain'	=> 'Url van extra stijlblad (css-bestand). Laat leeg wanneer niet gebruikt.',
				'attr'		=> ['maxlength'	=> 100],
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
					$config[$sub_name] = $app['config']->get($sub_name);
				}

				continue;
			}

			$config[$name] = $app['config']->get($name);
		}
	}
}

if ($post)
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

	foreach ($tab_panes[$active_tab]['inputs'] as $name => $input)
	{
		if (isset($input['cond']) && !$input['cond'])
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
			$value = ($value) ? '1' : '0';
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

			if (isset($validator['attr']['maxlength']) && strlen($value) > $validator['attr']['maxlength'])
			{
				$errors[] = 'Fout: de waarde mag maximaal ' . $validator['attr']['maxlength'] . ' tekens lang zijn.' . $err_n;
			}

			if (isset($validator['attr']['minlength']) && strlen($value) < $validator['attr']['minlength'])
			{
				$errors[] = 'Fout: de waarde moet minimaal ' . $validator['attr']['minlength'] . ' tekens lang zijn.' . $err_n;
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

			if (isset($validator['attr']['max']) && $value > $validator['attr']['max'])
			{
				$errors[] = 'Fout: de waarde mag maximaal ' . $validator['attr']['max'] . ' bedragen.' . $err_n;
			}

			if (isset($validator['attr']['min']) && $value < $validator['attr']['min'])
			{
				$errors[] = 'Fout: de waarde moet minimaal ' . $validator['attr']['min'] . ' bedragen.' . $err_n;
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
					$errors[] = 'Maximaal ' . $validator['max_inputs'] . ' E-mail adressen mogen ingegeven worden.' . $err_n;
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

			if (isset($validator['attr']['maxlength']) && strlen($value) > $validator['attr']['maxlength'])
			{
				$errors[] = 'Fout: de waarde mag maximaal ' . $validator['attr']['maxlength'] . ' tekens lang zijn.' . $err_n;
			}

			if (isset($validator['attr']['minlength']) && strlen($value) < $validator['attr']['minlength'])
			{
				$errors[] = 'Fout: de waarde moet minimaal ' . $validator['attr']['minlength'] . ' tekens lang zijn.' . $err_n;
			}
		}

		if ($validator['type'] === 'sortable')
		{

		}
	}

	if (!count($posted_configs))
	{
		$app['alert']->warning('Geen gewijzigde waarden.');
		cancel();
	}

	if (count($errors))
	{
		$app['alert']->error($errors);
		cancel();
	}

	$execute_post_actions = [];

	foreach ($posted_configs as $name => $value)
	{
		$app['xdb']->set('setting', $name, ['value' => $value]);

		$app['predis']->del($app['this_group']->get_schema() . '_config_' . $name);

		// prevent string too long error for eLAS database

		if ($validators[$name]['max_inputs'] > 1)
		{
			[$value] = explode(',', $value);
			$value = trim($value);
		}

		$value = substr($value, 0, 60);

		$app['db']->update('config', ['value' => $value, '"default"' => 'f'], ['setting' => $name]);

		$p_acts = is_array($post_actions[$name]) ? $post_actons[$name] : [$post_actions[$name]];

		foreach($p_acts as $p_act)
		{
			$execute_post_actions[$p_act] = true;
		}
	}

	if (isset($execute_post_actions['clear_eland_interlets_cache']))
	{
		$app['interlets_groups']->clear_eland_cache();
	}

	if (count($posted_configs) > 1)
	{
		$app['alert']->success('De instellingen zijn aangepast.');
	}
	else
	{
		$app['alert']->success('De instelling is aangepast.');
	}

	cancel();
}

$app['assets']->add(['sortable', 'summernote', 'rich_edit.js', 'config.js']);

$h1 = 'Instellingen';
$fa = 'gears';

include __DIR__ . '/include/header.php';

echo '<div>';
echo '<ul class="nav nav-pills" role="tablist">';

foreach ($tab_panes as $id => $pane)
{
	echo '<li role="presentation"';
	echo $id === $active_tab ? ' class="active"' : '';
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
	$active = $id === $active_tab ? ' active' : '';

	echo '<div role="tabpanel" ';
	echo 'class="tab-pane' . $active . '" id="' . $id . '">';

	echo '<form method="post" class="form form-horizontal">';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading"><h4>';
	echo $pane['lbl_pane'] ?? $pane['lbl'];
	echo '</h4></div>';

	echo '<ul class="list-group">';

	foreach ($pane['inputs'] as $name => $input)
	{
		if (isset($input['cond']) && !$input['cond'])
		{
			continue;
		}

		echo '<li class="list-group-item">';

		if (isset($input['max_inputs']) && $input['max_inputs'] > 1)
		{
			echo '<input type="hidden" value="' . $config[$name] . '" ';
			echo 'data-max-inputs="' . $input['max_inputs'] . '" ';
			echo 'name="' . $name . '">';

			$name_suffix = '_0';
		}
		else
		{
			$name_suffix = '';
		}

		if (isset($input['inline']))
		{
			$input_ary = [];

			if (isset($input['inputs']))
			{
				foreach ($input['inputs'] as $inline_name => $inline_input)
				{
					$str = '<input type="';
					$str .= $inline_input['type'] ?? 'text';
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

					$str .= isset($inline_input['required']) ? ' required' : '';

					$str .= '>';

					$input_ary[] = $str;
				}
			}

			echo '<p>' . vsprintf($input['inline'], $input_ary) . '</p>';
		}
		else if (isset($input['type']) && $input['type'] === 'sortable')
		{
			$v_options = $active = $inactive = [];
			$value_ary = explode(',', ltrim($config[$name], '+ '));

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
				echo '<p><small>' . $input['explain_top'] . '</small></p>';
			}

			echo '<div class="row">';

			echo '<div class="col-md-6">';
			echo '<div class="panel panel-default">';
			echo '<div class="panel-heading">';
			echo isset($input['lbl_active']) ? '<h5>' . $input['lbl_active'] . '</h5>' : '';
			echo '</div>';
			echo '<div class="panel-body">';
			echo '<ul id="list_active" class="list-group">';

			echo get_sortable_items($input['ary'], $v_options, $active, 'bg-success');

			echo '</ul>';
			echo '</div>';
			echo '</div>';
			echo '</div>'; // col

			echo '<div class="col-md-6">';
			echo '<div class="panel panel-default">';
			echo '<div class="panel-heading">';
			echo isset($input['lbl_inactive']) ? '<h5>' . $input['lbl_inactive'] . '</h5>' : '';
			echo '</div>';
			echo '<div class="panel-body">';
			echo '<ul id="list_inactive" class="list-group">';

			echo get_sortable_items($input['ary'], $v_options, $inactive, 'bg-danger');

			echo '</ul';
			echo '</div>';
			echo '</div>';
			echo '</div>'; // col

			echo '</div>'; // row

			echo '<input type="hidden" name="' . $name . '" ';
			echo 'value="' . $config[$name] . '" id="' . $name . '">';
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

			if (isset($input['addon']))
			{
				echo '<div class="input-group margin-bottom">';
				echo '<span class="input-group-addon">';
				echo $input['addon'];
				echo '</span>';
			}

			if (isset($input['type']) && $input['type'] === 'select')
			{
				echo '<select class="form-control" name="' . $name . '"';
				echo isset($input['required']) ? ' required' : '';
				echo '>';

				echo get_select_options($input['options'], $config[$name]);

				echo '</select>';
			}
			else if (isset($input['type']) && $input['type'] === 'textarea')
			{
				echo '<textarea name="' . $name . '" id="' . $name . '" class="form-control';
				echo isset($input['rich_edit']) ? ' rich-edit' : '';
				echo '" rows="4"';

				echo isset($input['attr']['maxlength']) ? '' : ' maxlength="2000"';
				echo isset($input['attr']['minlength']) ? '' : ' minlength="1"';
				echo isset($input['required']) ? ' required' : '';

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
				echo $input['type'] ?? 'text';
				echo '" class="form-control" ';
				echo 'name="' . $name . $name_suffix . '" ';
				echo 'id="' . $name . $name_suffix . '" ';
				echo 'value="' . $config[$name] . '"';

				echo isset($input['attr']['maxlength']) ? '' : ' maxlength="60"';
				echo isset($input['attr']['minlength']) ? '' : ' minlength="1"';
				echo isset($input['required']) ? ' required' : '';

				if (isset($input['attr']))
				{
					foreach ($input['attr'] as $attr_name => $attr_value)
					{
						echo ' ' . $attr_name . '="' . $attr_value . '"';
					}
				}

				echo '>';
			}

			echo isset($input['addon']) ? '</div>' : '';
			echo '</div>';
			echo '</div>';
		}

		if (isset($input['max_inputs']) && $input['max_inputs'] > 1)
		{
			echo '<div class="form-group hidden add-input">';
			echo '<div class="extra-field col-sm-9 col-sm-offset-3">';
			echo '<br>';
			echo '<span class="btn btn-default"><i class="fa fa-plus" ></i> ';
			echo $input['add_btn_text'] ?? 'Extra';
			echo '</span>';
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
	echo $app['form_token']->get_hidden_input();

	echo '</div>';

	echo '</div>';

	echo '</form>';

	echo '</div>';
}

echo '</div>';
echo '</div>';

include __DIR__ . '/include/footer.php';

function cancel()
{
	global $active_tab;

	header('Location: ' . generate_url('config', ['active_tab' => $active_tab]));
	exit;
}

function get_sortable_items($input_ary, $v_options, $items, $class)
{
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
			echo '<li class="list-group-item ' . $class . '" ';
			echo 'data-block="';
			echo $a;
			echo '" ';
			echo 'data-option="';
			echo $option;
			echo '" >';
			echo '<span class="lbl">';
			echo $lbl;
			echo '</span>';
			echo '</li>';

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

		echo '<li class="list-group-item ' . $class . '" ';
		echo 'data-block="' . $a . '" ';
		echo 'data-option="' . $option . '">';
		echo '<span class="lbl">';
		echo $lbl;
		echo '</span>';
		echo '&nbsp;&nbsp;';
		echo '<button type="button" class="btn btn-default ';
		echo 'dropdown-toggle" ';
		echo 'data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
		echo ' <span class="caret"></span>';
		echo '</button>';
		echo '<ul class="dropdown-menu">';

		foreach ($options as $k => $lbl)
		{
			echo '<li><a href="#" data-o="' . $k . '">';
			echo $lbl;
			echo '</a></li>';
		}

		echo '</ul></li>';
	}

	for($i = 0; $i < 5; $i++)
	{
		echo '<li class="list-group-item"></li>';
	}
}