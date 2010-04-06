/**
 * popup alert box
 * @access public
 * @return void
 **/
function pr(data){
	alert(data);
}

/**
 * write data to console
 * @access public
 * @return void
 **/
function debug(data){
	console.log(data);
}


require(
[
	"require",
	Infinitas.base + "libs/js/libs/metadata.js",
	Infinitas.base + "libs/js/libs/core.js",
	Infinitas.base + "libs/js/libs/form.js",
	Infinitas.base + "libs/js/libs/html.js"
],
function(require) {
	render();
});


/**
 *
 * @access public
 * @return void
 **/
function render(){
	$(document).ready(function(){
		urlDropdownSelects();
		doToolTips();

		$.FormHelper.checkboxToggleAll();
	});
}


/** core code */
/**
 *
 * @access public
 * @return void
 **/
function urlDropdownSelects(){
	/**
	 * Check for plugin dropdown changes
	 */
	$('.pluginSelect').change(function(){
		if ($(this).val().length != 0) {
			metaData = $.HtmlHelper.getParams($(this));
			metaData.params.plugin = $(this).val();
			$.HtmlHelper.requestAction(metaData, $.FormHelper.input);
		}
	});

	/**
	 * Check for controller dropdown changes
	 */
	$('.controllerSelect').change(function(){
		if ($(this).val().length != 0) {
			metaData = $.HtmlHelper.getParams($(this));
			metaData.params.plugin     = $('.pluginSelect').val();
			metaData.params.controller = $(this).val();
			$.HtmlHelper.requestAction(metaData, $.FormHelper.input);
		}
	});
}

function doToolTips(){
	$("*").tooltip({ 
	    track: true, 
	    delay: 0, 
	    showURL: false, 
	    opacity: 0.1, 
	    fixPNG: true, 
	    showBody: " - ", 
	    extraClass: "pretty fancy", 
	    top: -15, 
	    left: 5 
	}); 
}