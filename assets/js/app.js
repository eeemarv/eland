const $ = require('jquery');
// global.$ = global.jQuery = $;

require('bootstrap');
require('jquery-touchswipe/jquery.touchSwipe.min.js');

require('footable/js/footable.js');
require('footable/js/footable.sort.js');
require('footable/js/footable.filter.js');

// import './base.js';

var offcanvas = require('./functions/offcanvas');
var filter_remove_empty_inputs = require('./functions/filter_remove_empty_inputs');
var footable = require('./functions/footable');

$(document).ready(function() {
    offcanvas();
    filter_remove_empty_inputs();
});

require('../css/app.scss');
