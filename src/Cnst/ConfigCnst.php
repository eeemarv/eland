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
    ];

    const BLOCK_ARY = [
        'periodic_mail' => [
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
        ],

        'maxlimit'	=> [
            'addon'	=> '%config_currency%',
            'lbl'	=> 'Maximum Systeemslimiet',
            'type'	=> 'number',
            'explain'	=> 'Maximum Limiet die geldt voor alle Accounts,
                behalve voor die Accounts waarbij een Maximum Account
                Limiet ingesteld is. Kan leeg gelaten worden.',
            'default'	=> '',
        ],

        'balance_equilibrium'	=> [
            'addon'		=> '%config_currency%',
            'lbl'		=> 'Het uitstapsaldo voor actieve leden. ',
            'type'		=> 'number',
            'required'	=> true,
            'explain' 	=> 'Het saldo van leden met status uitstapper
                kan enkel bewegen in de richting van deze instelling.',
            'default'	=> '0',
        ],

        'msgs_days_default'	=> [
            'addon'	=> 'dagen',
            'lbl'	=> 'Standaard geldigheidsduur',
            'explain' => 'Bij aanmaak van nieuw vraag of aanbod wordt
                deze waarde standaard ingevuld in het formulier.',
            'type'	=> 'number',
            'attr'	=> ['min' => '1', 'max' => '1460'],
            'default'	=> '365',
        ],

        'msgcleanupenabled'	=> [
            'type'	=> 'checkbox',
            'default'	=> '0',
        ],

        'msgexpcleanupdays'	=> [
            'type'	=> 'number',
            'attr'	=> ['min' => '1', 'max' => '365'],
            'default'	=> '30',
        ],

        'msgexpwarnenabled'	=> [
            'type'	=> 'checkbox',
            'default'	=> '1',
        ],

        'systemname' => [
            'lbl'		=> 'Systeemsnaam',
            'required'	=> true,
            'addon_fa'	=> 'share-alt',
            'default'	=> '',
        ],

        'systemtag' => [
            'lbl'		=> 'E-mail tag',
            'explain'	=> 'Prefix tussen haken [tag] in onderwerp
                van alle E-mail-berichten',
            'required'	=> true,
            'addon_fa'	=> 'tag',
            'attr'		=> ['maxlength' => '30'],
            'default'	=> '',
        ],

        'logo'  => [
            'default'   => '',
        ],

        'currency'	=> [
            'lbl'		=> 'Naam van Munt (meervoud)',
            'required'	=> true,
            'addon_fa'	=> 'money',
            'default'	=> '',
        ],

        'currencyratio'	=> [
            'cond'		=> 'config_template_lets',
            'lbl'		=> 'Aantal per uur',
            'attr'		=> ['max' => '240', 'min' => '1'],
            'type'		=> 'number',
            'addon_fa'	=> 'clock-o',
            'explain'	=> 'Deze instelling heeft enkel betrekking op Tijdbanken.
                Zij is vereist voor eLAND interSysteem-verbindingen zodat de Systemen
                een gemeenschappelijke tijdbasis hebben.',
            'default'	=> '1',
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
        ],

        'saldofreqdays'	=> [
            'type'		=> 'number',
            'attr'		=> ['class' => 'sm-size', 'min' => '1', 'max' => '120'],
            'required'	=> true,
            'default'	=> '14',
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
        ],

        'contact_form_en' => [
            'type' => 'checkbox',
            'default'	=> '0',
        ],

        'contact_form_top_text' => [
            'lbl'	=> 'Tekst boven het contact formulier',
            'type'	=> 'textarea',
            'summernote'	=> true,
            'default'	=> '',
        ],

        'contact_form_bottom_text' => [
            'lbl'		=> 'Tekst onder het contact formulier',
            'type'		=> 'textarea',
            'summernote'	=> true,
            'default'	=> '',
        ],

        'registration_en' => [
            'type' => 'checkbox',
            'default'	=> '0',
        ],

        'registration_top_text' => [
            'lbl'	=> 'Tekst boven het inschrijvingsformulier',
            'type'	=> 'textarea',
            'summernote'	=> true,
            'explain' => 'Geschikt bijvoorbeeld om nadere uitleg
                bij de inschrijving te geven.',
            'default'	=> '',
        ],

        'registration_bottom_text' => [
            'lbl'		=> 'Tekst onder het inschrijvingsformulier',
            'type'		=> 'textarea',
            'summernote'	=> true,
            'explain'	=> 'Geschikt bijvoorbeeld om privacybeleid toe te lichten.',
            'default'	=> '',
        ],

        'registration_success_text'	=> [
            'lbl'	=> 'Tekst na succesvol indienen formulier.',
            'type'	=> 'textarea',
            'summernote'	=> true,
            'explain'	=> 'Hier kan je aan de gebruiker uitleggen
                wat er verder gaat gebeuren. <br>Als je Systeem een
                website heeft, is het nuttig om een link op te nemen
                om de gebruiker terug te voeren.',
            'default'	=> '',
        ],

        'registration_success_mail'	=> [
            'lbl'		=> 'Verstuur E-mail naar gebruiker bij succesvol indienen formulier',
            'type'		=> 'textarea',
            'summernote'	=> true,
            'attr'		=> [
                'data-template-vars' => '%map_template_vars%',
            ],
            'default'	=> '0',
        ],

        'news_order_asc'	=> [
            'type'	=> 'checkbox',
            'default'	=> '1',
        ],

        'forum_en'	=> [
            'type'	=> 'checkbox',
            'default'	=> '0',
        ],

        'newuserdays' => [
            'addon'		=> 'dagen',
            'lbl'		=> 'Periode dat een nieuw lid als instapper getoond wordt.',
            'type'		=> 'number',
            'attr'		=> ['min' => '0', 'max' => '365'],
            'required'	=> true,
            'default'	=> '7',
        ],

        'users_can_edit_username' => [
            'type'	=> 'checkbox',
            'default'	=> '0',
        ],

        'users_can_edit_fullname' => [
            'type'	=> 'checkbox',
            'default'	=> '0',
        ],

        'mailenabled'	=> [
            'type'	=> 'checkbox',
            'default'	=> '1',
        ],

        'maintenance'	=> [
            'type'	=> 'checkbox',
            'default'	=> '0',
        ],

        'template_lets'	=> [
            'type'	=> 'checkbox',
            'post_actions'	=> ['clear_eland_intersystem_cache'],
            'default'	=> '1',
        ],

        'interlets_en'	=> [
            'type'	=> 'checkbox',
            'post_actions'	=> ['clear_eland_intersystem_cache'],
            'default'	=> '0',
        ],

        'default_landing_page'	=> [
            'lbl'		=> 'Standaard landingspagina',
            'type'		=> 'select',
            'options'	=> 'landing_page',
            'required'	=> true,
            'addon_fa'	=> 'plane',
            'default'	=> 'messages',
        ],

        'homepage_url'	=> [
            'lbl'		=> 'Website url',
            'type'		=> 'url',
            'addon_fa'	=> 'link',
            'explain'	=> 'Titel en logo in de navigatiebalk linken naar deze url.',
            'default'	=> '',
        ],

        'date_format'	=> [
            'lbl'		=> 'Datum- en tijdweergave',
            'type'		=> 'select',
            'options'	=> 'date_format',
            'addon_fa'	=> 'calendar',
            'default'	=> '%e %b %Y, %H:%M:%S',
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

        'contact'	=> [
            'assets'    => [
                'codemirror',
                'summernote',
                'summernote_email.js',
            ],
            'lbl'	=> 'Contact',
            'lbl_pane'	=> 'Contact Formulier',
            'inputs'	=> [
                'li_1'	=> [
                    'inline' => '%input(contact_form_en)% contact formulier aan.',
                    'explain' => 'Het contactformulier kan je terugvinden
                        op <a href="%path_contact%">%path_contact%</a>.',
                ],
                'contact_form_top_text' => true,
                'contact_form_bottom_text' => true,
            ],
        ],

        'register'	=> [
            'assets'    => [
                'codemirror',
                'summernote',
                'summernote_email.js',
            ],
            'lbl'	=> 'Inschrijven',
            'lbl_pane'	=> 'Inschrijvingsformulier',
            'inputs'	=> [
                'li_1'	=> [
                    'inline' => '%input(registration_en)% inschrijvingsformulier aan.',
                    'explain' => 'Het registratieformulier kan je terugvinden op
                        <a href="%path_register%">%path_register%</a>. Plaats
                        deze link op je website.<br>Bij inschrijving wordt een
                        nieuwe gebruiker zonder code aangemaakt met status
                        info-pakket.<br>De admin krijgt een notificatie-email
                        bij elke inschrijving.',
                ],

                'registration_top_text' => true,
                'registration_bottom_text' => true,
                'registration_success_text'	=> true,
                'registration_success_mail'	=> true,
            ],
        ],

        'messages'		=> [
            'lbl'		=> 'Vraag en aanbod',
            'inputs'	=> [

                'msgs_days_default'	=> true,

                'li_1'	=> [
                    'inline' => '%input(msgcleanupenabled)% Ruim vervallen
                        vraag en aanbod op na %input(msgexpcleanupdays)% dagen.',
                ],

                'li_2'	=> [
                    'inline' => '%input(msgexpwarnenabled)% Mail een notificatie
                        naar de eigenaar van een vraag of aanbod bericht op
                        het moment dat het vervalt.',
                ],
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

        'forum'	=> [
            'lbl'	=> 'Forum',
            'inputs'	=> [
                'li_1'	=> [
                    'inline' => '%input(forum_en)% Forum aan.',
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
                    'inline'	=> '%input(template_lets)% Dit Systeem is
                        een Tijdbank (munt met tijdbasis).',
                ],

                'li_4'	=> [
                    'inline'	=> '%input(interlets_en)% Gebruik eLAND
                        interSysteem. Deze instelling is enkel geldig wanneer
                        hierboven "Tijdbank" geselecteerd is. eLAND
                        interSysteem is enkel mogelijk met
                        munten met gemeenschappelijke tijdbasis.',
                ],

                'default_landing_page'	=> true,
                'homepage_url'	=> true,
                'date_format'	=> true,
            ],
        ],
    ];
}