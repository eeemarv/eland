jQuery(function(){
	var $txt = $('[data-tag-txt]');
	var $exp = $('[data-tag-exp]');
	var $txt_color = $('[data-tag-txt-color]');
	var $bg_color = $('[data-tag-bg-color]');
	var $description = $('[data-tag-description]');

	$exp.css('color', $txt_color.val());
	$exp.css('border-color', $txt_color.val());
	$exp.css('background-color', $bg_color.val());

	$txt.on('change keyup', function(){
		$exp.text($(this).val());
	});
	$txt_color.on('change click', function(){
		$exp.css('color', $(this).val());
		$exp.css('border-color', $(this).val());
	});
	$bg_color.on('change click', function(){
		$exp.css('background-color', $(this).val());
	});
	$description.on('change keyup', function(){
		$exp.attr('title', $(this).val());
	});
});
