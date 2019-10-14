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
				['misc', ['fullscreen', 'codeview']]
			],

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
			}
		});

		$('form').submit(function(){
			$self.html($self.summernote('code'));
		});
	});
});
