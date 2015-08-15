$(document).ready(function() {
	$('ul#nav-tabs li a').click(function(e){
		var li = $(this).parent();
		li.siblings('li').removeClass('active');
		li.addClass('active');
		var hsh = li.find('a').attr('data-filter');
		$('input[type="hidden"][name="hsh"]').val(hsh);
		syncCombined();
		e.preventDefault();
	});

	$('#q').keyup(function(e){
		syncCombined();
	});

	$('#cat_hsh').change(function(e){
		syncCombined();
	});

	function syncCombined(){
		var cf = $('#combined-filter');
		var hsh = $('input[type="hidden"][name="hsh"]').val();
		hsh = (hsh.length > 0) ? ' ' + hsh : '';
		var cat_hsh = $('#cat_hsh').val();
		cat_hsh = (cat_hsh.length > 0) ? ' ' + cat_hsh : '';
		cf.val($('#q').val() + hsh + cat_hsh);
		cf.keyup();
		$('table tr > td > input[type="number"]').eq(0).keyup();
	}

	syncCombined();

	$('form[method!="post"]').submit(function(e) {
		e.preventDefault();
	});	
});
