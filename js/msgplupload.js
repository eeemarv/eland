$("#uploader").pluploadQueue({
	runtimes : 'html5',
	url : "/examples/upload",
	chunk_size : '1mb',
	unique_names : true,
	filters : {
		max_file_size : '10mb',
		mime_types: [
			{title : "Image files", extensions : "jpg, jpeg"},
		]
	},
	resize : {width : 400, height : 400, quality : 90}
});
