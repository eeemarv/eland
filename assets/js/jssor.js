import 'jssor-slider';
import jssor from './overall/jssor';

$.fn.extend({
    jssor: function(go_to_last_item) {
        return this.each(function(){
            jssor(this, go_to_last_item);
        });
    }
});

$(document).ready(function() {
    $('[data-jssor]').jssor(false);
});