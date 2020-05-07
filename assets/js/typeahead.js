import Bloodhound from 'bloodhound-js';
import 'corejs-typeahead/dist/typeahead.jquery.js';
import typeahead from './overall/typeahead';

$(document).ready(function() {
    typeahead(Bloodhound);
});
