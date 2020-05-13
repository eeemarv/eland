import 'footable/css/footable.core.css';

import 'footable/js/footable.js';
import 'footable/js/footable.sort.js';
import 'footable/js/footable.filter.js';

$(document).ready(function() {
    $('table[data-footable]').footable();
});
