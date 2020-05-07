const $ = require('jquery');

import 'bootstrap';
import 'jquery-touchswipe/jquery.touchSwipe.min.js';

import offcanvas from './overall/offcanvas';
import filter_remove_empty_inputs from './overall/filter_remove_empty_inputs';
import csv from './overall/csv';

$(document).ready(function() {
    offcanvas();
    filter_remove_empty_inputs();
    csv();
});

import '../css/app.scss';

const imagesContext = require.context('../images', true, /\.(png|jpg|jpeg|gif|ico|svg|webp)$/);
imagesContext.keys().forEach(imagesContext);
