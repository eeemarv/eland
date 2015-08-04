$(document).ready(function() {
	$('ul#nav-tabs li a').click(function(e){
		var li = $(this).parent();
		li.siblings('li').removeClass('active');
		li.addClass('active');
		var f = li.find('a').attr('data-filter');
		f = (f.length > 0) ? ' ' + f : '';
		var cf = $('#combined-filter');
		cf.val($('#q').val() + f);
		cf.keyup();
		e.preventDefault();
	});

	$('#q').keyup(function(e){
		var f = $(this).val();
		f += ' ' + $('ul#nav-tabs li.active a').attr('data-filter');
		var cf = $('#combined-filter');
		cf.val(f);
		cf.keyup();
	});

	var footableFilter = $('table').data('footable-filter');
	var q = $('#q').val();
	footableFilter.filter(q);

	$('form').submit(function( event ) {
		event.preventDefault();
	});	
});
