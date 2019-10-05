$(document).ready(function(){

	var $summernote = $('textarea.summernote');

	$summernote.each(function(){

		var $self = $(this);

		$self.summernote({
			minHeight: 200,
			lang: 'nl-NL',

			 toolbar: [
				['style', ['bold', 'italic', 'underline', 'clear']],
				['fontsize', ['fontsize']],
				['para', ['ul', 'ol', 'paragraph']],
				['insert', ['hr', 'link']],
				['misc', ['fullscreen']]
			],
		});

		$('form').submit(function(){
			$self.html($self.summernote('code'));
		});
	});
});
