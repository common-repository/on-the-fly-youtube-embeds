// http://gotochriswest.com/blog/2011/07/25/javascript-string-prototype-replaceall/
String.prototype.replaceAll = function(target, replacement) {
  return this.split(target).join(replacement);
};

(function($) {
	$(document).ready(function(){
	
	$('.page-slug input').bind('change blur keypress keydown keyup', function(e){

		var slug = this.value.toLowerCase().replaceAll(/ /, '-');
		if( !slug ){
			slug = 'video';
		}

		$('.example-url .slug').html( slug );
	});

	});	
})( jQuery );