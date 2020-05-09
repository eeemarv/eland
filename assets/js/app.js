const $ = require('jquery');

import 'bootstrap';
import 'jquery-touchswipe/jquery.touchSwipe.min.js';

import offcanvas from './overall/offcanvas';
import filter_auto_submit from './overall/filter_auto_submit';
import filter_remove_empty_inputs from './overall/filter_remove_empty_inputs';
import csv from './overall/csv';
import item_access_input_cache from './overall/item_access_input_cache';
import table_select from './overall/table_select';

$(document).ready(function() {
    offcanvas();
    filter_auto_submit();
    filter_remove_empty_inputs();
    csv();
    item_access_input_cache();
    table_select();
});

import '../css/app.scss';

const imagesContext = require.context('../images', true, /\.(png|jpg|jpeg|gif|ico|svg|webp)$/);
imagesContext.keys().forEach(imagesContext);
