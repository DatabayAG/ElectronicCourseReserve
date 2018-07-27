il.ElectronicCourseReserveObjectIcon = (function (scope) {
	'use strict';

	var pub = {}, pro = {};
	
	pub.config = {
		replacementImage : null,
		replaceImage     : null
	}; 

	pub.setConfig = function(replaceImage, withImage) {
		pub.config.replaceImage = replaceImage;
		pub.config.replacementImage = withImage;
	};
	
	pub.replace = function(){
		if(pub.config.replacementImage !== null && pub.config.replaceImage !== null){
			$(pub.config.replaceImage).attr('src', pub.config.replacementImage);
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