

$(document).ready(function(){

	$('div.rich-edit').each(function(){

		$(this).summernote({
			lang: 'nl-NL'
		});

/*
		var name = $(this).prop('name');

		CKEDITOR.replace($(this), {
			language: 'nl',
			customConfig: '',
			toolbarGroups: [
				{"name":"basicstyles","groups":["basicstyles"]},
				{"name":"links","groups":["links"]},
				{"name":"paragraph","groups":["list","blocks"]},
				{"name":"document","groups":["mode"]}
			],
			removeButtons: 'Anchor,Subscript,Superscript,Styles,Specialchar,Table'
		});

*/

	});
});


/*

$(document).ready(function(){

		CKEDITOR.replace('registration_top_text', {
			language: 'nl',
			customConfig: '',
			toolbarGroups: [
				{"name":"basicstyles","groups":["basicstyles"]},
				{"name":"links","groups":["links"]},
				{"name":"paragraph","groups":["list","blocks"]},
				{"name":"document","groups":["mode"]}
			],
			removeButtons: 'Anchor,Subscript,Superscript,Styles,Specialchar,Table'
		});
});

*/
