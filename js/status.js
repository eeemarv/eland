window.addEvent('domready', function() {
	//jsnotify('requesting status');

	var statusreq = new Request({
	    url: '/renderstatus.php',
	    method: 'get',
	    onRequest: function(){
        	//jsnotify('requesting status');
    		},
	    onSuccess: function(responseText){
		if(responseText != '---'){
        		jsnotify(responseText);
		}
    		},
    	    onFailure: function(){
    		}
	});
	
	var StatusLoop = function() {
		//jsnotify('looping');
		statusreq.send();
	};

	StatusLoop.periodical(5000);
	statusreq.send();

});

