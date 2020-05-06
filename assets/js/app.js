const $ = require('jquery');

import 'bootstrap';
import 'jquery-touchswipe/jquery.touchSwipe.min.js';

import offcanvas from './functions/offcanvas';
import filter_remove_empty_inputs from './functions/filter_remove_empty_inputs';

$(document).ready(function() {
    offcanvas();
    filter_remove_empty_inputs();
});

import '../css/app.scss';

const imagesContext = require.context('../images', true, /\.(png|jpg|jpeg|gif|ico|svg|webp)$/);
imagesContext.keys().forEach(imagesContext);
