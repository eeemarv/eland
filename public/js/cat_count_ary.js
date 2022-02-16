jQuery(function(){

	$('select[data-cat-count-ary]').each(function(){
		var $select = $(this);
		var cat_count_ary = $select.data('cat-count-ary');
		$select.children('option').each(function(){
			var $option = $(this);
			var option_value = $option.val();
			if (option_value === 'null' && !cat_count_ary['null']){
				$option.remove();
			} else if (cat_count_ary[option_value]){
				$option.append(' <span style="color: #009">(' +  cat_count_ary[option_value] + ')</span>');
			}
		});
	});
});
