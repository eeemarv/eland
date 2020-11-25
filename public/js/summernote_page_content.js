jQuery(function(){

	var $summernote = $('textarea.summernote');
	var image_upload = $summernote.data('image-upload');

	$summernote.each(function(){

		var $self = $(this);

		$self.summernote({
			minHeight: 200,
			lang: 'nl-NL',

			 toolbar: [
				['style_1', ['style']],
				['style_2', ['bold', 'italic', 'underline', 'clear']],
				['fontsize', ['fontsize']],
				['para', ['ul', 'ol', 'paragraph']],
				['table', ['table']],
				['insert', ['link', 'picture']],
				['misc', ['fullscreen', 'codeview']]
			],

			styleTags: ['p', 'quote', 'h1', 'h2', 'h3', 'h4'],

			codemirror: {
				theme: 'monokai',
				lineNumbers: true,
				indentWithTabs: false,
				matchBrackets: true,
				autoCloseBrackets: true,
				matchTags: true,
				showTrailingSpace: true,
				autoCloseTags: true,
				newlineAndIndentContinueMarkdownList: true,
				foldGutter: true,
				styleActiveLine: true,
				colorpicker: true
			},

			callbacks: {
				onImageUpload: function(images){
					var data = new FormData();
					data.append('image', images[0]);

					$.post(image_upload, data)
					.done(function(ret){
						var $insert = $('<img>')
							.attr('src', ret.base_url + ret.file)
							.attr('data-base-url', ret.base_url)
							.attr('data-file', ret.file);
						$self.summernote('insertNode', $insert);
					}).fail(function(){
						alert('Afbeelding opladen mislukt.');
					});
				}
			}
		});

		$('form').on('submit', function(){
			$self.html($self.summernote('code'));
		});
	});
});
