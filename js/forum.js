$(document).ready(function(){

	CKEDITOR.replace('content', {
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
