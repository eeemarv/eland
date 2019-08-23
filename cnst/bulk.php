<?php declare(strict_types=1);

namespace cnst;

use cnst\role as cnst_role;
use cnst\status as cnst_status;

class bulk
{
    const USER_TPL_VARS = [
        'naam' 					=> 'name',
        'volledige_naam'		=> 'fullname',
        'saldo'					=> 'saldo',
        'account_code'			=> 'letscode',
    ];

    const USER_TABS = [
        'fullname_access'	=> [
            'lbl'				=> 'Zichtbaarheid Volledige Naam',
            'item_access'	=> true,
        ],
        'adr_access'		=> [
            'lbl'		=> 'Zichtbaarheid adres',
            'item_access'	=> true,
        ],
        'mail_access'		=> [
            'lbl'		=> 'Zichtbaarheid E-mail adres',
            'item_access'	=> true,
        ],
        'tel_access'		=> [
            'lbl'		=> 'Zichtbaarheid telefoonnummer',
            'item_access'	=> true,
        ],
        'gsm_access'		=> [
            'lbl'		=> 'Zichtbaarheid GSM-nummer',
            'item_access'	=> true,
        ],
        'comments'			=> [
            'lbl'		=> 'Commentaar',
            'type'		=> 'text',
            'string'	=> true,
            'fa'		=> 'comment-o',
        ],
        'accountrole'		=> [
            'lbl'		=> 'Rechten',
            'options'	=> cnst_role::LABEL_ARY,
            'string'	=> true,
            'fa'		=> 'hand-paper-o',
        ],
        'status'			=> [
            'lbl'		=> 'Status',
            'options'	=> cnst_status::LABEL_ARY,
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
            'type'		=> 'number',
            'fa'		=> 'arrow-down',
        ],
        'maxlimit'			=> [
            'lbl'		=> 'Maximum Account Limiet',
            'type'		=> 'number',
            'fa'		=> 'arrow-up',
        ],
        'cron_saldo'		=> [
            'lbl'	    => 'Periodieke Overzichts E-mail (aan/uit)',
            'type'	    => 'checkbox',
        ],
    ];

    const TPL_INPUT =  <<<'TPL'
    <div class="form-group">
    <label for="%name%" class="control-label">%label%</label>
    <div class="input-group">
    <span class="input-group-addon">
    <span class="fa fa-%fa%"></span></span>
    <input type="%type%" id="%name%" name="%name%" class="form-control"%attr%>
    </div>
    </div>
    TPL;

    const TPL_CHECKBOX = <<<'TPL'
    <div class="form-group">
    <label for="%name%" class="control-label">
    <input type="%type%" id="%name%" name="%name%" %attr%>
    &nbsp;%label%</label></div>
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
    </div>
    TPL;
}
