il.ElectronicCourseReserveObjectIcon = (function (scope) {
	'use strict';

	var pub = {}, pro = {};
	
	pub.config = {
		replacementImage          : null,
		replaceIdentifierOfSource : null
	}; 

	pub.setConfig = function(replaceImage, withImage) {
		pub.config.replaceIdentifierOfSource = replaceImage;
		pub.config.replacementImage  = withImage;
	};

	pub.replace = function(){
		if(pub.config.replacementImage !== null && pub.config.replaceIdentifierOfSource !== null){
			$(pub.config.replaceIdentifierOfSource).attr('src', pub.config.replacementImage);
		}
	};

	pub.protect = pro;
	return pub;

}(il));

$( document ).ready(function() {
	setTimeout(function(){
		il.ElectronicCourseReserveObjectIcon.replace()
	}, 20);
});