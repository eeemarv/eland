$(document).ready(function() {
    var footableFilter = $('table').data('footable-filter');
    var q = $('#q').val();
    footableFilter.filter(q);
    
	$('form').submit(function( event ) {
		event.preventDefault();
	});
});

