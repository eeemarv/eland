import Bloodhound from 'bloodhound-js';
import 'corejs-typeahead/dist/typeahead.jquery.js';
import typeahead from './functions/typeahead';

$(document).ready(function() {
    typeahead(Bloodhound);
});
