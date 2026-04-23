jQuery(function(){
	$('[data-prototype]').each(function(){
		var $cnt = $(this);
		var $btn_add = $cnt.parent().children('[data-btn-add]');

		$btn_add.on('click', function(){
			var count = $cnt.children().length;
			var new_widget = $cnt.attr('data-prototype');
			new_widget = new_widget.replace(/__name__/g, count);
			$cnt.append(new_widget);
			if (count > 3){
				$(this).hide();
			}
		});
	});
});
