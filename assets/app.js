import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */

import $ from 'jquery';

// Expose jQuery globally for Bootstrap 3 and legacy plugins
window.jQuery = window.$ = $;

import "./styles/bs3.scss";
import "./styles/typeahead.css";
import "./styles/body.css";
// extra styles to migrate to Bootstrap 5
import "./styles/bs5.css";
import "./styles/margin5.css";
import "./styles/nav.css";
import "./styles/form.css";
import "./styles/jssor.css";
import "./styles/map.css";
import "./styles/jqplot.css";
import "./styles/img-upload.css";
import "./styles/pan-sub.css";
import "./styles/num.css";
import "./styles/eland-index.css";
import "./styles/nav-config.css";
import "./styles/list-active.css";
import "./styles/note.css";
import "./styles/cms-edit.css";
import "./styles/tags.css";

import 'datatables.net-bs/css/dataTables.bootstrap.min.css';
import 'datatables.net-responsive-bs/css/responsive.bootstrap.min.css';
import "./styles/datatables-custom.css";

import 'leaflet/dist/leaflet.min.css';

//import "autocompleter/autocomplete.min.css";
import "./styles/autocomplete-bs3.css";

import "./styles/print.css";

console.log('== assets/app.js');
