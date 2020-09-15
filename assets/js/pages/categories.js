export default function(){
	var sortable_categories = [].slice.call(document.querySelectorAll('[data-sortable]'));
	for (var i = 0; i < sortable_categories.length; i++) {
		new Sortable(sortable_categories[i], {
			group: {
				name: 'sortable_categories',
				pull: function (to, from, el) {
					if (to.el.hasAttribute('data-sort-base')){
						return true;
					}
					if (to.el.parentElement.parentElement.hasAttribute('data-sort-base')
						&& $(el).find('[data-id]').length === 0){
						return true;
					}
					return false;
				}
			},
			animation: 150,
			fallbackOnBody: true,
			swapThreshold: 0.65,
			onAdd: function (evt){
				var is_base = evt.item.parentElement.hasAttribute('data-sort-base');
				if (is_base){
					evt.item.classList.add('list-group-item-info');
				} else {
					evt.item.classList.remove('list-group-item-info');
				}
				$(evt.item).find('[data-del-btn').remove();
			}
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
};
