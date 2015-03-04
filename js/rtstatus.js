window.addEvent('domready', function() {
    var evtSource = new EventSource("/realtimestatus.php");

    evtSource.addEventListener("status", function(e) {
        var obj = JSON.parse(e.data);
        jsnotify(obj.message);
    }, false);
});
