$(document).ready(function(){
	$('#logout_link').on('click', function() {
	    var choice = confirm('Are you sure?');
	    if(choice === true) {
	        window.location.href='index.php?logout';
	    }
	    return false;
	});
	$('#logout_link').css('cursor', 'pointer');
});
