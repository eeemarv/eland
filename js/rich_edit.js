$(document).ready(function(){

	$('textarea.rich-edit').summernote({

		lang: 'nl-NL',

		 toolbar: [
			['style', ['bold', 'italic', 'underline', 'clear']],
			['fontsize', ['fontsize']],
			['para', ['ul', 'ol', 'paragraph']],
			['insert', ['hr', 'link']],
			['misc', ['fullscreen', 'codeview']]
		  ]

	});

	$('form').submit(function(){

		$('textarea.rich-edit').each(function(){

			$(this).html($(this).summernote('code'));

		});

	});

});


