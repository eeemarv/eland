jQuery(function(){
	var sortable_tags = [].slice.call(document.querySelectorAll('[data-sortable]'));
	for (var i = 0; i < sortable_tags.length; i++) {
		new Sortable(sortable_tags[i], {
			animation: 150,
			fallbackOnBody: true,
			swapThreshold: 0.65,
		});
	}

	$('form[method="post"]').on('submit', function(event) {
		var $base = $('[data-sort-base]');
		function serialize($sort){
			var ary = [];
			$sort.children('[data-id]').each(function(){
				var $item = $(this);
				var children = serialize($item.children('[data-sortable]'));
				var ary_item = {id: $item.data('id')};
				if (children.length > 0){
					ary_item.children = children;
				}
				ary.push(ary_item);
			});
			return ary;
		}
		$('[data-tags-input]').val(JSON.stringify(serialize($base)));
	});
});
