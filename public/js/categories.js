$(document).ready(function(){
	var sortable_categories = [].slice.call(document.querySelectorAll('[data-sortable]'));
	for (var i = 0; i < sortable_categories.length; i++) {
		var el = sortable_categories[i];
		var has_messages = $(el).data('has-messages');
		var has_categories = $(el).data('has-categories');
		var put_en = !(has_messages || has_categories);
		new Sortable(el, {
			group: {
				name: 'sortable_categories',
				put: put_en
			},
			animation: 150,
			fallbackOnBody: true,
			swapThreshold: 0.65
		});
	}

	$('form[method="post"]').submit(function(event) {
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
		$('[data-categories-input]').val(JSON.stringify(serialize($base)));
	});
});
