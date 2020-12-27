<?php declare(strict_types=1);

namespace App\Cnst;

class ConfigCnst
{
    const TAG = [
        'input'    => [
            'open'  => '%input(',
            'close' => ')%',
        ],
    ];

    const MAP_TEMPLATE_VARS = [
        'voornaam' 			=> 'first_name',
        'achternaam'		=> 'last_name',
        'postcode'			=> 'postcode',
    ];

    const LANDING_PAGE_OPTIONS = [
        'messages'		=> 'Vraag en aanbod',
        'users'			=> 'Leden',
        'transactions'	=> 'Transacties',
        'news'			=> 'Nieuws',
        'docs'          => 'Documenten',
        'forum'         => 'Forum',
    ];

    const BLOCK_ARY = [
        'messages'		=> [
            'recent'	=> 'Recent vraag en aanbod',
        ],
        'intersystem'	=> [
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
        'messages_self' => [
            'all'       => 'Lijst eigen vraag en aanbod',
        ],
    ];

    const INPUTS = [

        'minlimit'	=> [
            'addon'	=> '%config_currency%',
            'lbl'	=> 'Minimum Systeemslimiet',
            'type'	=> 'number',
            'explain'	=> 'Minimum Limiet die geldt voor alle Accounts,
                behalve voor die Accounts waarbij een Minimum Account
                Limiet ingesteld is. Kan leeg gelaten worden.',
            'default'	=> '',
            'path'  => 'accounts.limits.global.min',
        ],

        'maxlimit'	=> [
            'addon'	=> '%config_currency%',
            'lbl'	=> 'Maximum Systeemslimiet',
            'type'	=> 'number',
            'explain'	=> 'Maximum Limiet die geldt voor alle Accounts,
                behalve voor die Accounts waarbij een Maximum Account
                Limiet ingesteld is. Kan leeg gelaten worden.',
            'default'	=> '',
            'path'  => 'accounts.limits.global.max',
        ],

        'balance_equilibrium'	=> [
            'addon'		=> '%config_currency%',
            'lbl'		=> 'Het uitstapsaldo voor actieve leden. ',
            'type'		=> 'number',
            'required'	=> true,
            'explain' 	=> 'Het saldo van leden met status uitstapper
                kan enkel bewegen in de richting van deze instelling.',
            'default'	=> '0',
            'path'      => 'accounts.equilibrium',
        ],

        'systemname' => [
            'lbl'		=> 'Systeemsnaam',
            'required'	=> true,
            'addon_fa'	=> 'share-alt',
            'default'	=> '',
            'path'      => 'system.name',
        ],

        'systemtag' => [
            'lbl'		=> 'E-mail tag',
            'explain'	=> 'Prefix tussen haken [tag] in onderwerp
                van alle E-mail-berichten',
            'required'	=> true,
            'addon_fa'	=> 'tag',
            'attr'		=> ['maxlength' => '30'],
            'default'	=> '',
            'path'      => 'mail.tag',
        ],

        'logo'  => [
            'default'   => '',
            'path'      => 'system.logo',
        ],

        'currency'	=> [
            'lbl'		=> 'Naam van Munt (meervoud)',
            'required'	=> true,
            'addon_fa'	=> 'money',
            'default'	=> '',
            'path'      => 'transactions.currency.name',
        ],

        'currencyratio'	=> [
            'cond'		=> 'config_template_lets',
            'lbl'		=> 'Aantal per uur',
            'attr'		=> ['max' => '240', 'min' => '1'],
            'type'		=> 'number',
            'addon_fa'	=> 'clock-o',
            'explain'	=> 'Deze instelling heeft enkel betrekking op systemen met een
                tijd-gebaseerde munt.
                Zij is vereist voor eLAND interSysteem-verbindingen zodat de Systemen
                een gemeenschappelijke tijdbasis hebben.',
            'default'	=> '1',
            'path'      => 'transactions.currency.per_hour_ratio',
        ],

        'admin'	=> [
            'lbl'	=> 'Algemeen admin/beheerder',
            'explain_top'	=> 'Krjgt algemene E-mail notificaties
                van het Systeem',
            'attr' 	=> ['minlength' => '7'],
            'type'	=> 'email',
            'addon_fa'		=> 'envelope-o',
            'max_inputs'	=> 5,
            'add_btn_text' 	=> 'Extra E-mail Adres',
            'default'	=> '',
            'path'      => 'mail.addresses.admin',
            'is_ary'    => true,
        ],

        'support'	=> [
            'lbl'	=> 'Support / Helpdesk',
            'explain_top'	=> 'Krjgt E-mail berichten
                van het Help- en Contactformulier.',
            'attr'	=> ['minlength' => '7'],
            'type'	=> 'email',
            'addon_fa'		=> 'envelope-o',
            'max_inputs'	=> 5,
            'add_btn_text'	=> 'Extra E-mail Adres',
            'default'	=> '',
            'path'      => 'mail.addresses.support',
            'is_ary'    => true,
        ],

        'saldofreqdays'	=> [
            'type'		=> 'number',
            'attr'		=> ['class' => 'sm-size', 'min' => '1', 'max' => '120'],
            'required'	=> true,
            'default'	=> '14',
            'path'      => 'periodic_mail.days',
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
            'block_ary'			=> 'periodic_mail',
            'default'		    => '+messages.recent',
            'path'              => 'periodic_mail.user.layout',
            'is_ary'            => true,
        ],

        'news_order_asc'	=> [
            'type'	=> 'checkbox',
            'default'	=> '1',
            'path'      => 'news.sort.asc',
        ],

        'forum_en'	=> [
            'type'	=> 'checkbox',
            'default'	=> '0',
            'path'      => 'forum.enabled',
        ],

        'newuserdays' => [
            'addon'		=> 'dagen',
            'lbl'		=> 'Periode dat een nieuw lid als instapper getoond wordt.',
            'type'		=> 'number',
            'attr'		=> ['min' => '0', 'max' => '365'],
            'required'	=> true,
            'default'	=> '7',
            'path'      => 'users.new.days',
        ],

        'users_can_edit_username' => [
            'type'	=> 'checkbox',
            'default'	=> '0',
            'path'      => 'users.fields.username.self_edit',
        ],

        'users_can_edit_fullname' => [
            'type'	=> 'checkbox',
            'default'	=> '0',
            'path'      => 'users.fields.full_name.self_edit',
        ],

        'mailenabled'	=> [
            'type'	=> 'checkbox',
            'default'	=> '1',
            'path'      => 'mail.enabled',
        ],

        'maintenance'	=> [
            'type'	=> 'checkbox',
            'default'	=> '0',
            'path'      => 'system.maintenance_en'
        ],

        'template_lets'	=> [
            'type'	=> 'checkbox',
            'post_actions'	=> ['clear_eland_intersystem_cache'],
            'default'	=> '1',
            'path'      => 'transactions.currency.timebased_en',
        ],

        'default_landing_page'	=> [
            'lbl'		=> 'Standaard landingspagina',
            'type'		=> 'select',
            'options'	=> 'landing_page',
            'required'	=> true,
            'addon_fa'	=> 'plane',
            'default'	=> 'messages',
            'explain'   => 'De standaard pagina waar men terecht komt na login.
                De betreffende module moet ingeschakeld zijn. (Zie "Modules")',
            'path'      => 'system.default_landing_page',
        ],

        'homepage_url'	=> [
            'lbl'		=> 'Website url',
            'type'		=> 'url',
            'addon_fa'	=> 'link',
            'explain'	=> 'Titel en logo in de navigatiebalk linken naar deze url.',
            'default'	=> '',
            'path'      => 'system.website_url',
        ],

        'date_format'	=> [
            'lbl'		=> 'Datum- en tijdweergave',
            'type'		=> 'select',
            'options'	=> 'date_format',
            'addon_fa'	=> 'calendar',
            'default'	=> '%e %b %Y, %H:%M:%S',
            'path'      => 'system.date_format',
        ],
    ];

    const TAB_PANES = [

        'system-name'	=> [
            'lbl'	=> 'Systeemsnaam',
            'inputs' => [
                'systemname' => true,
                'systemtag' => true,
            ],
        ],

        'logo'  => [
            'lbl'       => 'Logo',
            'inputs'    => [],
            'assets'    => [
                'fileupload',
                'upload_image.js',
            ],
        ],

        'modules'   => [
            'route' => 'config_ext_modules',
            'lbl'   => 'Modules',
        ],

        'currency'		=> [
            'lbl'	=> 'Munteenheid',
            'inputs'	=> [
                'currency'	=> true,
                'currencyratio'	=> true,
            ],
        ],

        'mail-addr'	=> [
            'assets'        => [
                'config_max_inputs.js',
            ],
            'lbl'		=> 'E-Mail Adressen',
            'explain'	=> 'Er moet minstens één E-mail adres voor elk
                type ingesteld zijn.
                Maak het vakje leeg om een E-mail
                adres te verwijderen.',
            'inputs'	=> [
                'admin'	    => true,
                'support'	=> true,
            ]
        ],

        'balance'		=> [
            'lbl'		=> 'Saldo',
            'inputs'	=> [
                'minlimit'	=> true,
                'maxlimit'	=> true,
                'balance_equilibrium'	=> true,
            ],
        ],

        'periodic-mail'		=> [
            'assets'    => [
                'sortable',
                'config_periodic_mail.js',
            ],
            'lbl'	=> 'Overzichts E-mail',
            'lbl_pane'	=> 'Periodieke Overzichts E-mail',
            'inputs' => [
                'li_1'	=> [
                    'inline' => 'Verstuur de Periodieke Overzichts E-mail
                        om de %input(saldofreqdays)% dagen',
                    'explain' => 'Noot: Leden kunnen steeds ontvangst
                        van de Periodieke Overzichts E-mail aan- of afzetten
                        in hun profielinstellingen.',
                ],

                'periodic_mail_block_ary' => true,
            ],
        ],

        'users'	=> [
            'lbl'	=> 'Leden',
            'inputs'	=> [
                'newuserdays' => true,

                'li_2' => [
                    'inline' => '%input(users_can_edit_username)% Leden
                        kunnen zelf hun Gebruikersnaam aanpassen.',
                ],

                'li_3' => [
                    'inline' => '%input(users_can_edit_fullname)% Leden
                        kunnen zelf hun Volledige Naam aanpassen.',
                ],
            ],
        ],

        'news'	=> [
            'lbl'	=> 'Nieuws',
            'inputs'	=> [
                'li_1'	=> [
                    'inline' => '%input(news_order_asc)% Sorteer nieuwsberichten
                        chronologisch op agendadatum.',
                ],
            ],
        ],

        'system'	=> [
            'lbl'		=> 'Systeem',
            'inputs'	=> [

                'li_1'	=> [
                    'inline'	=> '%input(mailenabled)% E-mail functionaliteit aan:
                        het Systeem verstuurt E-mails.',
                ],

                'li_2' => [
                    'inline' => '%input(maintenance)% Onderhoudsmodus:
                        alleen admins kunnen inloggen.',
                ],

                'li_3' => [
                    'inline'	=> '%input(template_lets)% Dit Systeem heeft
                        een munt met tijdbasis.',
                ],

                'default_landing_page'	=> true,
                'homepage_url'	=> true,
                'date_format'	=> true,
            ],
        ],
    ];
}