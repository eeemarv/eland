$(document).ready(function(){
	var $access_panels = $('div[data-access-control]');

	$access_panels.find('input[type="radio"]').prop('required', false);

/*
	$access_panels.on('shown.bs.collapse', function(){
		console.log('show');
		$(this).find('input[type="radio"]').prop('required', true);
	});

	$access_panels.on('hidden.bs.collapse', function(){
		console.log('hide');
		$(this).find('input[type="radio"]').prop('required', false);
	});
	*
*/
});
