$(document).ready(function() {
    var footableFilter = $('table').data('footable-filter');
    var q = $('#filter').val();
    footableFilter.filter(q);
    
	$('form').submit(function( event ) {
		event.preventDefault();
	});
});

