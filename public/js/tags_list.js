jQuery(function(){
	var sortable_tags = [].slice.call(document.querySelectorAll('[data-sortable]'));
	for (var i = 0; i < sortable_tags.length; i++) {
		new Sortable(sortable_tags[i], {
			animation: 150,
			fallbackOnBody: true,
			swapThreshold: 0.65,
		});
	}

	$('form[method="post"]').on('submit', function() {
		var $base = $('[data-sort-base]');
		var ary = [];
		$base.children('[data-id]').each(function(){
			ary.push($(this).data('id'));
		});
		$('[data-tags-input]').val(JSON.stringify(ary));
	});
});
