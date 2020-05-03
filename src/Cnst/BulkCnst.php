<?php declare(strict_types=1);

namespace App\Cnst;

use App\Cnst\RoleCnst;
use App\Cnst\StatusCnst;

class BulkCnst
{
    const MOLLIE_TPL_VARS = [
        'betaal_link'           => 'payment_link',
        'bedrag'                => 'amount',
        'omschrijving'          => 'description',
        'naam' 					=> 'name',
        'volledige_naam'		=> 'fullname',
        'account_code'			=> 'code',
    ];

    const USER_TPL_VARS = [
        'naam' 					=> 'name',
        'volledige_naam'		=> 'fullname',
        'saldo'					=> 'balance',
        'account_code'			=> 'code',
    ];

    const USER_TABS = [
        'fullname_access'	=> [
            'lbl'				=> 'Zichtbaarheid Volledige Naam',
            'string'        => true,
            'item_access'	=> true,
        ],
        'adr_access'		=> [
            'lbl'		=> 'Zichtbaarheid adres',
            'item_access'	=> true,
            'contact_abbrev' => 'adr',
        ],
        'mail_access'		=> [
            'lbl'		=> 'Zichtbaarheid E-mail adres',
            'item_access'	=> true,
            'contact_abbrev' => 'mail',
        ],
        'tel_access'		=> [
            'lbl'		=> 'Zichtbaarheid telefoonnummer',
            'item_access'	=> true,
            'contact_abbrev' => 'tel',
        ],
        'gsm_access'		=> [
            'lbl'		=> 'Zichtbaarheid GSM-nummer',
            'item_access'	=> true,
            'contact_abbrev' => 'gsm',
        ],
        'comments'			=> [
            'lbl'		=> 'Commentaar',
            'type'		=> 'text',
            'string'	=> true,
            'fa'		=> 'comment-o',
        ],
        'role'		=> [
            'lbl'		=> 'Rechten',
            'options'	=> RoleCnst::LABEL_ARY,
            'string'	=> true,
            'fa'		=> 'hand-paper-o',
        ],
        'status'			=> [
            'lbl'		=> 'Status',
            'options'	=> StatusCnst::LABEL_ARY,
            'fa'		=> 'star-o',
        ],
        'admincomment'		=> [
            'lbl'		=> 'Commentaar van de Admin',
            'type'		=> 'text',
            'string'	=> true,
            'fa'		=> 'comment-o',
        ],
        'minlimit'			=> [
            'lbl'		=> 'Minimum Account Limiet',
            'explain'   => 'Tip: laat het veld leeg om de minimum limiet te wissen.
                Bij accounts zonder individuele minimum limiet is de minimum systeemslimiet van toepassing (wanneer ingesteld).',
            'type'		=> 'number',
            'fa'		=> 'arrow-down',
        ],
        'maxlimit'			=> [
            'lbl'		=> 'Maximum Account Limiet',
            'explain'   => 'Tip: laat het veld leeg om de maximum limiet te wissen.
                Bij accounts zonder individuele maximum limiet is de maximum systeems limiet van toepassing (wanneer ingesteld).',
            'type'		=> 'number',
            'fa'		=> 'arrow-up',
        ],
        'periodic_overview_en'		=> [
            'lbl'	    => 'Periodieke Overzichts E-mail (aan/uit)',
            'type'	    => 'checkbox',
        ],
    ];

/*
    const TPL_CHECKBOX_ITEM = <<<'TPL'
    <label for="sel[%id%]">&nbsp;&nbsp;
    <input type="checkbox" name="sel[%id%]" id="sel[%id%]" value="1"%attr%>
    &nbsp;&nbsp;%label%
    </label>';
    TPL;
*/

    const TPL_CHECKBOX_ITEM = <<<'TPL'
    <div class="custom-control custom-checkbox">
    <input type="checkbox" class="custom-control-input" id="sel[%id%]" name="sel[%id%]" value="1"%attr%>
    <label class="custom-control-label" for="sel[%id%]">%label%</label>
    </div>
    TPL;

    const TPL_INPUT =  <<<'TPL'
    <div class="form-group">
    <label for="%name%" class="control-label">%label%</label>
    <div class="input-group">
    <span class="input-group-prepend">
    <span class="input-group-text">
    <i class="fa fa-%fa%"></i>
    </span>
    </span>
    <input type="%type%" id="%name%" name="%name%" class="form-control"%attr%>
    </div>
    %explain%
    </div>
    TPL;

/*
    const TPL_CHECKBOX = <<<'TPL'
    <div class="form-group">
    <label for="%name%" class="control-label">
    <input type="checkbox" id="%name%" name="%name%"%attr%>
    &nbsp;%label%</label></div>
    TPL;
*/

    const TPL_CHECKBOX = <<<'TPL'
    <div class="custom-control custom-checkbox form-group">
    <input type="checkbox" class="custom-control-input" id="%name%" name="%name%"%attr%>
    <label class="custom-control-label" for="%name%">%label%</label>
    </div>
    TPL;

    const TPL_SELECT = <<<'TPL'
    <div class="form-group">
    <label for="%name%" class="control-label">
    %label%</label>
    <div class="input-group">
    <span class="input-group-prepend">
    <span class="input-group-text">
    <i class="fa fa-%fa%"></i>
    </span>
    </span>
    <select name="%name%" id="%name%"
    class="form-control"%attr%>
    %options%
    </select>
    </div>
    %explain%
    </div>
    TPL;

    const TPL_SELECT_BUTTONS = <<<'TPL'
    <div class="card bg-light mb-3" id="bulk_actions">
    <div class="card-body">
    <input type="button"
    class="btn btn-default btn-lg border border-dark"
    data-table-sel="invert"
    value="Selectie omkeren">&nbsp;
    <input type="button"
    class="btn btn-default btn-lg border border-dark"
    data-table-sel="all"
    value="Selecteer alle">&nbsp;
    <input type="button"
    class="btn btn-default btn-lg border border-dark"
    data-table-sel="none"
    value="De-selecteer alle">
    </div>
    </div>
    TPL;
}
