$(document).ready(function(){

	$('textarea.rich-edit').summernote({

		lang: 'nl-NL'

	});

	$('form').submit(function(){

		$('textarea.rich-edit').each(function(){

			$(this).html($(this).summernote('code'));

		});

	});

});


