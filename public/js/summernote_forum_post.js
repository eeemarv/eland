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
				['insert', ['link']]
			],
			fontSizes: ['12', '14', '16', '18', '20', '24']
		});

		if ($self.prop('disabled')){
			$self.summernote('disable');
		}

		$('form').on('submit', function(){
			$self.html($self.summernote('code'));
		});
	});
});
