jQuery(function(){
	$('ul#nav-tabs li a').on('click', function(e){
		var li = $(this).parent();
		li.siblings('li').removeClass('active');
		li.addClass('active');
		var hsh = li.find('a').attr('data-filter');
		$('input[type="hidden"][name="hsh"]').val(hsh);
		syncCombined();
		e.preventDefault();
	});

	$('#q').on('keyup', function(e){
		syncCombined();
	});

	function syncCombined(){
		var cf = $('#combined-filter');
		var hsh = $('input[type="hidden"][name="hsh"]').val();
		hsh = (hsh.length > 0) ? ' ' + hsh : '';
		cf.val($('#q').val() + hsh);
		cf.trigger('keyup');
		$('table tr > td > input[type="number"]').eq(0).trigger('keyup');
	}

	syncCombined();

	$('form[method!="post"]').on('submit', function(e){
		e.preventDefault();
	});
});
