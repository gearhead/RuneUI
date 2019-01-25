/*
 *  SliderNav - A Simple Content Slider with a Navigation Bar
 *  Copyright 2015 Monji Dolon, http://mdolon.com/
 *  Released under the MIT, BSD, and GPL Licenses.
 *  More information: http://devgrow.com/slidernav
 */
$.fn.sliderNav = function(options) {
	console.log("init slider");
	var items = $("#database-entries").find("li");
	var height = $(".btnlist-bottom").position().top - $("#database-entries").position().top;
	var stdheight = $('#database-entries').height();
	console.log("slider height from", stdheight, "to", height);
	if (stdheight < height) {
		return;
	}
	$('#database-entries').height(height);

	var defaults = { items: ["a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z"], debug: false, height: null, event: 'pointerover'};
	var opts = $.extend(defaults, options); var o = $.meta ? $.extend({}, opts, $$.data()) : opts; var slider = $(this); $(slider).addClass('slider');
	$('.slider-content li:first', slider).addClass('selected');
	$(slider).append('<div class="slider-nav"><ul></ul></div>');
	for(var i = 0; i < o.items.length; ++i) $('.slider-nav ul', slider).append("<li><a alt='#"+o.items[i]+"'>"+o.items[i]+"</a></li>");
	$('.slider-content, .slider-nav', slider).css('top',$("#database-entries").position().top);
	$('.slider-content, .slider-nav', slider).css('height',height);
	$('.slider-content, .slider-nav li', slider).css('height',(height-2)/26);
	$('.slider-content, .slider-nav li', slider).css('font-size','90%');
	$('#database-entries').css('overflow-y', 'scroll');
	$('#database-entries').css('padding-right', $('.slider-nav').width());

	var last='';
	console.log("slider loop", items.length);
	for (var i = 0; i < items.length; i++) {
		if ($(items[i]).attr("data-path") === undefined) {
			continue;
		}
		var name = $(items[i]).find("span").text();
		if (name !== undefined) {
			var initial = name[0].toUpperCase();
			if (initial != last) {
				last = initial;
				console.log("now at", last, items[i]);
				anchor = "<li id='" + initial + "' class='alpha-anchor'>" + initial + "</li>";
				$(items[i]).before(anchor);
			}
		}
	}

	$('.slider-nav a', slider).on(opts.event, function(event){
		var target = $(this).attr('alt').toUpperCase();
		var cOffset = $('#database-entries').offset().top;
		var tOffset = $('#database-entries '+target).offset().top;
		var pScroll = (tOffset - cOffset);
		$('#database-entries').stop().animate({scrollTop: "+=" + pScroll + "px"});
	});
};
