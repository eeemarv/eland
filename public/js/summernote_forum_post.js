jQuery(function(){

	var $summernote = $('textarea.summernote');

	$summernote.each(function(){

		var $self = $(this);

		$self.summernote({
			minHeight: 200,
			lang: 'nl-NL',
			toolbar: [
				['style_2', ['bold', 'italic', 'underline', 'clear']],
				['fontsize', ['fontsize']],
				['para', ['ul', 'ol', 'paragraph']],
				['insert', ['hr', 'link']]
			],
			fontSizes: ['12', '14', '16', '18', '24']
		});

		$('form').on('submit', function(){
			$self.html($self.summernote('code'));
		});
	});
});
