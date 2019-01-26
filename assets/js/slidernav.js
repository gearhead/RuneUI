/*
 *  SliderNav - A Simple Content Slider with a Navigation Bar
 *  Copyright 2015 Monji Dolon, http://mdolon.com/
 *  Released under the MIT, BSD, and GPL Licenses.
 *  More information: http://devgrow.com/slidernav
 */

function addAlphaAnchors(items) {
	var last='';
	var is_ordered = true;

	console.log("slider loop", items.length);
	for (var i = 0; i < items.length; i++) {
		if ($(items[i]).attr("data-path") === undefined) {
			continue;
		}
		var name = $(items[i]).find("span").text();
		if (name !== undefined && name.length > 0) {
			var initial = name[0].toUpperCase();
			if (initial < 'A' || initial > 'Z') {
				continue;
			}
			if (initial != last) {
				if (initial < last) {
					console.log("back from", last, "to", initial, ", list is unordered");
					is_ordered = false;
					$(items[i]).parent().find("li.alpha-anchor").remove();
					break;
				}
				last = initial;
				console.log("now at", last, items[i]);
				anchor = "<li id='" + initial + "' class='alpha-anchor'>" + initial + "</li>";
				$(items[i]).before(anchor);
			}
		}
	}

	return is_ordered;
}

function sliderRemove() {
	$(".slider-nav").remove();
	$('#database-entries').css('padding-right', 0);
}

function sliderSetListHeight(items, o) {
	o.height = $(".btnlist-bottom").position().top - $("#database-entries").position().top;
	var stdheight = 0;
	for (var i = 0; i < items.length && stdheight < o.height; i++) {
		stdheight += $(items[i]).height();
	}
	console.log("slider height from", stdheight, "to", o.height);
	if (stdheight < o.height) {
		return false;
	}
	$('#database-entries').height(o.height);
	return true;
}

function sliderSetupLayout(slider, o) {
	var input = "<input orient=vertical type=range min=1 max=26 value=26 id='slider-range'>";
	$(slider).append('<div class="slider-nav"><ul>' + input + '</ul></div>');
	for(var i = 0; i < o.items.length; ++i) $('.slider-nav ul', slider).append("<li><a alt='#"+o.items[i]+"'>"+o.items[i]+"</a></li>");
	$('.slider-content, .slider-nav', slider).css('top',$("#database-entries").position().top);
	$('.slider-content, .slider-nav', slider).css('height',o.height);
	$('#slider-range').css('top',$("#database-entries").position().top);
	$('#slider-range').css('height',o.height);
	$('.slider-content, .slider-nav li', slider).css('height',(o.height-2)/26);
	$('.slider-content, .slider-nav li', slider).css('font-size','90%');
	$('#database-entries').css('overflow-y', 'scroll');
	$('#database-entries').css('padding-right', $('.slider-nav').width());
	$('#slider-range').css('width',$(".slider-nav").width());
}

$.fn.sliderNav = function(options) {
	console.log("init slider");
	var defaults = { items: ["a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z"], debug: false, height: null};
	var opts = $.extend(defaults, options); var o = $.meta ? $.extend({}, opts, $$.data()) : opts; var slider = $(this); $(slider).addClass('slider');
	var items = $("#database-entries").children("li");

	/* check if list is ordered and add anchors if so */
	var is_ordered = addAlphaAnchors(items);
	if (!is_ordered) {
		sliderRemove();
		return;
	}

	/* set list height and check if it overflows */
	var is_overflowing = sliderSetListHeight(items, o);
	if (!is_overflowing) {
		sliderRemove();
		return;
	}

	sliderSetupLayout(slider, o);

	$('#slider-range', slider).on("input", function(event){
		var value = 26 - $(this).val();
		var current = opts.items[value];
		var target = current.toUpperCase();
		console.log("value", value, "current", current, "target", target);
		if ($('#database-entries #'+target).offset() === undefined) {
			return;
		}
		var cOffset = $('#database-entries').offset().top;
		var tOffset = $('#database-entries #'+target).offset().top;
		var pScroll = (tOffset - cOffset);
		$('#database-entries').stop().animate({scrollTop: "+=" + pScroll + "px"});
	});
};
