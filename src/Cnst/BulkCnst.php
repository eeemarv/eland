<?php declare(strict_types=1);

namespace App\Cnst;

use App\Cnst\RoleCnst;
use App\Cnst\StatusCnst;

class BulkCnst
{
    const MOLLIE_TPL_VARS = [
        'naam' 					=> 'name',
        'volledige_naam'		=> 'full_name',
        'account_code'			=> 'code',
        'betaal_link'           => 'payment_link',
        'bedrag'                => 'amount',
        'omschrijving'          => 'description',
    ];

    const USER_TPL_VARS = [
        'naam' 					=> 'name',
        'volledige_naam'		=> 'full_name',
        'account_code'			=> 'code',
    ];

    const USER_TABS = [
        'full_name_access'	=> [
            'lbl'			=> 'Zichtbaarheid Volledige Naam',
            'string'        => true,
            'item_access'	=> true,
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
        'admin_comments'		=> [
            'lbl'		=> 'Commentaar van de Admin',
            'type'		=> 'text',
            'string'	=> true,
            'fa'		=> 'comment-o',
        ],
        'min_limit'			=> [
            'lbl'		=> 'Minimum Account Limiet',
            'explain'   => 'Tip: laat het veld leeg om de minimum limiet te wissen.
                Bij accounts zonder individuele minimum limiet is de minimum systeemslimiet van toepassing (wanneer ingesteld).',
            'type'		=> 'number',
            'fa'		=> 'arrow-down',
        ],
        'max_limit'			=> [
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

    const TPL_CHECKBOX_ITEM = <<<'TPL'
    <span class="custom-checkbox">
    <label for="sel[%id%]">
    <input type="checkbox" name="sel[%id%]" id="sel[%id%]" value="1"%attr%>&nbsp;&nbsp;
    %label%
    </label>
    </span>
    TPL;

    const TPL_INLINE_NUMBER_INPUT = <<<'TPL'
    <input type="number" name="%name%"
    id="%name%" class="sm-size"
    value="%value%"%attr%>
    TPL;

    const TPL_INPUT_FA =  <<<'TPL'
    <div class="form-group">
    <label for="%name%" class="control-label">%label%</label>
    <div class="input-group">
    <span class="input-group-addon">
    <span class="fa fa-%fa%"></span></span>
    <input type="%type%" id="%name%" name="%name%" value="%value%" class="form-control"%attr%>
    </div>
    %explain%
    </div>
    TPL;

    const TPL_INPUT_ADDON =  <<<'TPL'
    <div class="form-group">
    <label for="%name%" class="control-label">%label%</label>
    <div class="input-group">
    <span class="input-group-addon">
    %addon%</span>
    <input type="%type%" id="%name%" name="%name%" class="form-control" value="%value%"%attr%>
    </div>
    %explain%
    </div>
    TPL;

    const TPL_CHECKBOX = <<<'TPL'
    <div class="form-group">
    <div class="custom-checkbox">
    <label for="%name%" class="control-label">
    <input type="checkbox" id="%name%" name="%name%"%attr%>
    &nbsp;%label%</label>
    </div></div>
    TPL;

    const TPL_CHECKBOX_DIV_ATTR = <<<'TPL'
    <div class="form-group"%div_attr%>
    <div class="custom-checkbox">
    <label for="%name%" class="control-label">
    <input type="checkbox" id="%name%" name="%name%"%attr%>
    &nbsp;%label%</label>
    </div></div>
    TPL;

    const TPL_SELECT = <<<'TPL'
    <div class="form-group">
    <label for="%name%" class="control-label">
    %label%</label>
    <div class="input-group">
    <span class="input-group-addon">
    <span class="fa fa-%fa%"></span></span>
    <select name="%name%" id="%name%"
    class="form-control"%attr%>
    %options%
    </select>
    </div>
    %explain%
    </div>
    TPL;

    const TPL_SELECT_BUTTONS = <<<'TPL'
    <div class="panel panel-default" id="bulk_actions">
    <div class="panel-heading">
    <input type="button"
    class="btn btn-default btn-lg"
    data-table-sel="invert"
    value="Selectie omkeren">&nbsp;
    <input type="button"
    class="btn btn-default btn-lg"
    data-table-sel="all"
    value="Selecteer alle">&nbsp;
    <input type="button"
    class="btn btn-default btn-lg"
    data-table-sel="none"
    value="De-selecteer alle">
    </div>
    </div>
    TPL;

    const TPL_RADIO_INLINE = <<<'TPL'
    <label class="radio-inline">
    <input type="radio" name="%name%"
    value="%value%"%attr%>
    &nbsp;%label%</label>
    TPL;

    const TPL_CHECKBOX_BTN_INLINE = <<<'TPL'
    <label class="checkbox-inline" for="%name%">
    <input type="checkbox" name="%name%" value="1" id="%name%"%attr%>
    &nbsp;
    <span class="btn btn-%btn_class%">%label%</span></label>
    TPL;

    const TPL_CHECKBOX_INLINE = <<<'TPL'
    <label class="checkbox-inline" for="%name%">
    <input type="checkbox" name="%name%" id="%name%"%attr%>
    &nbsp;
    %label%</label>
    TPL;
}
