import 'footable/css/footable.core.css';

import 'footable/js/footable';
import 'footable/js/footable.sort';
import 'footable/js/footable.filter';

$(document).ready(function() {
    $('table[data-footable]').footable();
});
