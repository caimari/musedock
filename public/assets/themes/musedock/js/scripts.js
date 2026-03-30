/*
 * All Scripts Used in this Zipprich Theme
 * Author & Copyright: VictorThemes
 * URL: http://themeforest.net/user/VictorThemes
 */

(function($){
	'use strict';

/*============  1.STICKY HEADER FUNTION ===========*/
function zipprich_sticky_header(){
	$(".ziph-mainmenu  ul.sub-menu li.menu-item-has-children").hover(function(){
		$("body").css("overflow-x","inherit");
	},function(){
		$("body").css("overflow-x","hidden");
	});
	$('.ziph-header-main-menu').scrollToFixed();
	$(window).on("scroll",function(){
		var availableCont, availablenavCont, stickyHeader, activeposHeader;
			availableCont = $("body").has(".ziph-header_top").length;
			availablenavCont = $("body").has(".ziph-header_styl-2").length;
			stickyHeader = $(".ziph-header-main-menu");
			activeposHeader = 100;
		if(true == availableCont){
			activeposHeader = $(".ziph-header_top").outerHeight() + 50;
		}
		if(activeposHeader <= $(this).scrollTop()){
			stickyHeader.addClass("ziph-header_sticky_active");
		}
		else{
			stickyHeader.removeClass("ziph-header_sticky_active");
		}
		return false;
	});
}

/*============ 2.COUNTER UP  INIT FUNCTION ===========*/
function zipprich_counter_up_init(){
	$('.ziph-counter').counterUp({
		delay: 10,
		time: 1000
	});

}

/*============ 3.COUNTER UP  INIT FUNCTION ===========*/
function zipprich_faqaccordion_class(){
	var faqCont = $("body").has(".ziph-faq").length;
	if(true == faqCont){
		function zipprich_check_expand(){
			setTimeout(function(){
				$(".ziph-faq").each(function(){
					var $this = $(this);
					$(this)
					.attr(
						"data-show",$this.find(".ziph-faq_btn").attr("aria-expanded")
						);
				});
			},30)

		}

		zipprich_check_expand();

		$(".ziph-faq").on("click",function(){
			zipprich_check_expand();
		});
	}
	return;
}

/*============4.TESTIMONIAL CAROUSEL INIT ===========*/
function zipprich_testimonial_carousel(){
	var $hasCont = $("body").has(".ziph-testimonial_carousel").length;
	if(true == $hasCont){
		$(".ziph-testimonial_carousel").each(function(){
			var $carousel, $loopItem;
			$carousel = $(this);
			$loopItem = $(".ziph-testimonial_carousel  .ziph-single_tetimnal");
			$loopItem = ($loopItem.length > 1) ? true : false;

			$carousel.owlCarousel({
				autoplay:($carousel.data("autoplay") !== undefined) ? $carousel.data("autoplay") : false,
				loop:$loopItem,
				items: 1,
				center:($carousel.data("center") !== undefined) ? $carousel.data("center") : false,
				dots:($carousel.data("dots") !== undefined) ? $carousel.data("dots") : false,
				nav: ($carousel.data("nav") !== undefined) ? $carousel.data("nav") : false,
				navText:['<i class="fa fa-angle-left"></i>', '<i class="fa fa-angle-right"></i>'],
				animateOut: 'fadeOutUp',
 				animateIn: 'fadeInUp'
			});

		});
	}
	return;
}

/*============  5.HOME PAGE CLINT LOGOS CAROUSEL INIT FUNTION ===========*/
function zipprich_clint_logos_carousel(){
	var $upEventCar = $("body").has(".ziph-clitlogos_carousel").length;
	if(true == $upEventCar){
		$(".ziph-clitlogos_carousel").each(function(){
			var $carousel, $loopItem ,$items, $items_tablet, $items_mobile_wide,$items_mobile;

				$carousel = $(this);
				$items = ($carousel.data("items") !== undefined) ? $carousel.data("items") : 5;
				$items_tablet = ($carousel.data("items-tablet") !== undefined) ? $carousel.data("items-tablet") : 2;
				$items_mobile_wide = ($carousel.data("items-mobile-wide") !== undefined) ? $carousel.data("items-mobile-wide") :2;
				$items_mobile = ($carousel.data("items-mobile") !== undefined) ? $carousel.data("items-mobile") : 1;

				$loopItem = $(".grop-ucoming_evnt_carousel  .grop-ucoming_evnt_sigl_item");
				$loopItem = $loopItem = ($loopItem.length > $items) ? true : false;

				$carousel.owlCarousel({
					autoplay:($carousel.data("autoplay") !== undefined) ? $carousel.data("autoplay") : false,
					loop:$loopItem,
					items: $items,
					dots:($carousel.data("dots") !== undefined) ? $carousel.data("dots") : false,
					nav:($carousel.data("nav") !== undefined) ? $carousel.data("nav") : false,
					navText:['<i class="fa fa-angle-left"></i>', '<i class="fa fa-angle-right"></i>'],
					margin: ($carousel.data("margin") !== undefined) ? $carousel.data("margin") : 0,
					responsive:{
						0:{
							items:$items_mobile,
						},
						450:{
							items:$items_mobile_wide,
						},
						750:{
							items:$items_tablet,
						},
						970:{
							items:$items,
						},
						1170:{
							items:$items,

						}
					}

				});
		});
	}
	return false;
}

/*============  6.FUNCTION  MOBIL MENU===========*/
function zipprich_mobil_menu(){
	$('.ziph-mobil_menu').slimmenu({
		resizeWidth: '991',
		collapserTitle: '',
		animSpeed: 'medium',
		easingEffect: null,
		indentChildren: false,
		childrenIndenter: '&nbsp;',
		expandIcon: '<i class="fa fa-angle-down"></i>',
		collapseIcon: '<i class="fa fa-angle-up"></i>'
	});

	$(".ziph-mobil_menu li.menu-item-has-children > a").on("click",function(){
		$(this).next(".sub-toggle").trigger("click");
	});
}

/*============  7.FUNCTION  VIDEO ===========*/
function zipprich_video_func(){
	$("body").fitVids();
	$("a.ziph-vd_play").on("click",function(e){
		e.preventDefault();

		$(this).parents(".ziph-vds_warp").addClass("ziph-vd_show");
		$("#ziph-video").attr("src",function(i,url){
			return url+"&autoplay=1";
		});
		return false;
	});
}

/*============  8.FUNCTION  VIDEO ===========*/
function zipprich_someOther_func(){
	function zipprich_widget_border(){
		$(".ziph-side-widget  select, .ziph-side-widget  select, input[type='search'], .widget_calendar ")
		.closest('.ziph-side-widget').addClass("ziph-wigtHas_border");
	}
	zipprich_widget_border();

	$(".ziph-mainmenu li.menu-item-has-children > a, .ziph-mobil_menu li.menu-item-has-children > a")
	.on("click",function(e){
		e.preventDefault();
	});
	$(".ziph-wigtResent_posts")
	.closest('.ziph-side-widget')
	.addClass('widget_recent_entries');

	$("#bridge .logincontainer .checkbox + div a.btn")
	.insertAfter("#bridge .logincontainer .checkbox label");

	$("#bridge .logincontainer .checkbox label")
	.append("<span class='ziph-bridgeRMCheckBox_bg'></span>");

	$(".whmpress_domain_search_bulk form .bulk-options .extention-selection label")
	.append("<span class='ziph-whmpressBulkDomains_Radiobg'></span>");

	$(".whmpress_domain_search_bulk form .bulk-options .extentions > div input")
	.after("<span class='ziph-whmpressBulkDomains_Checkbg'></span>");

	var tableclass = $(".ziph-singlePost_content table, .ziph-page_content table, .comment-content table").attr("class");

	if(undefined == tableclass || null == tableclass){
		$(".ziph-singlePost_content  table, .ziph-page_content  table, .comment-content table").addClass("table table-bordered");
	}
	return;
}

/*============= 9.SOME CUSTOM  STYLE FOR CONTACT FORM  FUNCTION ===========*/
function zipprich_contact_form_input(){
	// input range and number filed
		var $rangeSelect,$numberFi;
		$rangeSelect = $(".ziph-stylest-contact-form input[type='range'], .wpcf7-form-control-wrap input[type='range']");
		$numberFi = $(".ziph-stylest-contact-form input[type='number'] , .wpcf7-form-control-wrap input[type='number']");

		$numberFi.on("change",function(){
			var max = parseInt($(this).attr('max'));
			var min = parseInt($(this).attr('min'));

		    if ($(this).val() > max)
		    {
		          $(this).attr("disabled","disabled");
		          $(this).val(max);
		    }
		    if($(this).val() < min){
		    	$(this).attr("disabled","disabled");
		        $(this).val(min);
		    }

			$rangeSelect.val($(this).val());

		});

		$rangeSelect.on("change",function(){
			var $rangeVal = $(this).val();
			$numberFi.removeAttr("disabled");
			$numberFi.val($rangeVal);
		});

	// input file upload
	var fileSelec = $(".ziph-stylest-contact-form input[type='file'], .wpcf7-form-control-wrap input[type='file']");
	fileSelec.parent().addClass("ziph-file-upload");
	fileSelec.before("<span class='ziph-file-btn'>Upload</span>");
	fileSelec.after("<span class='ziph-file-name'>No file selected</span>");
	fileSelec.on("change",function(){
		var fileName = $(this).val();
		$(this).next(".ziph-file-name").text(fileName);
	});

  // input checkbox
  var $checkBoxSelector = $(".ziph-stylest-contact-form input[type='checkbox'], .wpcf7-checkbox label input[type='checkbox']");
  $checkBoxSelector.after("<span class='ziph-checkbox-btn'></span>");

  // input radio
  var $radioSelector = $(".ziph-stylest-contact-form input[type='radio'], .wpcf7-radio label input[type='radio']");
  $radioSelector.after("<span class='ziph-radio-btn'></span>");
}

/*============ 10.WOOCOMMERCE QUANTITY BUTTON FUNCTION ===========*/
function zipprich_woocom_spinner(){
	var $content = $("body")
	.has(".quantity input.qty[type=number], .quantity input.qty[type=number]")
	.length;
	if(true == $content){
	var fidSelector = $('.woocommerce .quantity input.qty[type=number], .woocommerce-page .quantity input.qty[type=number]');
	    fidSelector.before('<span class="ziph-qty-up"></span>');
	    fidSelector.after('<span class="ziph-qty-down"></span>');
	    setTimeout(function(){
			$('.woocommerce .quantity input.qty[type=number], .woocommerce-page .quantity input.qty[type=number]').each(function(){
				var minNumber = $(this).attr("min");
				var maxNumber = $(this).attr("max");
				$(this).prev('.ziph-qty-up').on('click', function() {
					if($(this).next("input.qty[type=number]").val() == maxNumber){
						return false;
					}else{
						$(this).next("input.qty[type=number]").val( parseInt($(this).next("input.qty[type=number]").val(), 10) + 1);
					}
				});
				$(this).next('.ziph-qty-down').on('click', function() {
					if($(this).prev("input.qty[type=number]").val() == minNumber){
						return false;
					}else{
						$(this).prev("input.qty[type=number]").val( parseInt($(this).prev("input.qty[type=number]").val(), 10) - 1);
					}
				});
			});

	    },100);
    }
    return;
}

/*============ 11.WOOCOMMERCE CHCKBOX AND RADIO INPUT STYLE INIT FUNCTION ===========*/
function zipprich_input_chckbox_style(){
	$(".woocommerce-checkout, .woocommerce-page, .woocommerce")
	.find("input[type='checkbox']")
	.wrap("<span class='ziph-woocCheckbox_group'></span>")
	.after("<span class='ziph-woo-check-style'></span>");

}

/*============ 12.MATCH HEIGHT ===========*/
function zipprich_match_height(){
	var $content = $("body").has(".masonry-loop").length;
	if(true == $content){
	    var container = document.querySelector('.masonry-loop');
	    //create empty var msnry
	    var msnry;
	    // initialize Masonry after all images have loaded
	    imagesLoaded( container, function() {
	        msnry = new Masonry( container, {
	            itemSelector: '.masonry-entry'
	        });
	    });
	}
}

/*============ 13.Woocommerce update cart button ===========*/
function zipprich_woo_update_cart_button(){
	$( '.woocommerce-cart .quantity' ).on( 'click', function() {
		$( '.shop_table.cart' ).closest( 'form' ).find( 'button[name="update_cart"]' ).removeProp( 'disabled');
	});
}

/*============ 14.Mailchimp email field icon ===========*/
function zipprich_mailchimp_icon(){
	$( '.mc4wp-form-fields' ).append('<p><button type="submit"><span class="fa fa-angle-right"></span></button></p>');
}

/*============ 15.Domain Layout Fix ===========*/
function zipprich_domain_grid_layout_fix(){
	var $content = $(".ziph-tabs").has(".ziph-choose-icon").length;
	if(true == $content){
		$(".ziph-choose-icon").addClass('col-md-5  ziph-flt_right');
		$(".ziph-choose-icon").prev().addClass('col-md-7');
		$(".ziph-choose-icon").next().addClass('col-md-7');
		$(".ziph-choose-icon").parent().addClass('row');
	}
}

/*============ 16.Woo Lost Pass Style ===========*/
function zipprich_woo_lost_pass(){
	var $content = $(".woocommerce").has(".woocommerce-ResetPassword.lost_reset_password").length;
	if(true == $content){
		$(".woocommerce-ResetPassword.lost_reset_password").wrapAll('<div class="u-columns col2-set" id="customer_login"><div class="u-column1 col-1"></div></div>');
	}
}

/*============ 17.Woo Login Style ===========*/
function zipprich_woo_login(){
	var $content = $(".woocommerce").has("#customer_login").length;
	if(false == $content){
		$(".woocommerce-form.woocommerce-form-login").wrapAll('<div class="u-columns col2-set" id="customer_login"><div class="u-column1 col-1"></div></div>');
		$(".woocommerce-form.woocommerce-form-login").before('<h2>Login</h2>');
		$(".u-columns.col2-set").prev('h2').hide();
	}
}

/*============ 17.Woo External Product Button Style ===========*/
function zipprich_woo_external_product(){
	var $content = $(".woocommerce").has(".button.product_type_external").length;
	if(true == $content){
		$(".button.product_type_external,.button.product_type_grouped").addClass('add_to_cart_button');
	}
}

/*============ 18.Mega Menu Style ===========*/
function zipprich_mega_menu(){
	$(".ziph-mainmenu .megamenu-item ul").addClass('row ziph-megamenu_warp');
	$(".ziph-mainmenu .megamenu-item ul li, .ziph-mobil_menu .megamenu-item ul li").addClass('col-md-4 text-center ziph-megmenuSingle_cont');
}

/*============ 19.WCHMS Menu Fix ===========*/
function zipprich_wcmhs_menu(){
	$('#bridge #main-menu .dropdown').on('click', function(){
		$(this).toggleClass('open');
	});
	$('#bridge #home-banner .btn.search').removeClass('search').addClass('btn-warning');
	$('#bridge #home-banner .btn.transfer').removeClass('transfer').addClass('btn-info');
}

/*============ 20.WCHMS Bulk Domain ===========*/
function getUrlParam(param){
  param = param.replace(/([\[\](){}*?+^$.\\|])/g, "\\$1");
  var regex = new RegExp("[?&]" + param + "=([^&#]*)");
  var url   = decodeURIComponent(window.location.href);
  var match = regex.exec(url);
  return match ? match[1] : "";
}
function zipprich_wcmhs_bulkdomain(){
	var param = getUrlParam("search_bulk_domain");
	if(param){
		var search_box = $("body").has('textarea[name="bulkdomains"]').length;
		if(search_box){
			$('textarea[name="bulkdomains"]').val(param);
		}
	}
}

/*============ 21.Woo search fix ===========*/
function zipprich_woo_search(){
	var $content = $("body").has(".woocommerce-product-search").length;
	if(true == $content){
		$('.widget_product_search').addClass('widget_search').removeClass('ziph-wigtHas_border');
		$('.woocommerce-product-search').addClass('ziph-wigtsrch_form');
		$('.woocommerce-product-search input.search-field').addClass('ziph-wigtsrch_field');
	}
}

/*============ 22.Header search ===========*/
function zipprich_header_search(){
	var $content = $("body").has(".ziph-hdricon-search").length;
	if(true == $content){
		$('.ziph-hdricon-search a').on('click', function(e) {
			e.preventDefault();
			$('.ziph-menu_form').toggle();
		});
	}
}

/*============ 23.Woo Login Register buttons added ===========*/
function zipprich_woo_login_register(){
	var $content = $(".woocommerce").has(".u-column2.col-2").length;
	if(true == $content){
		$(".u-column1.col-1").append('<a href="#" class="woocommerce-Button button button-register">Register</a>');
		$(".u-column2.col-2").append('<a href="#" class="woocommerce-Button button button-login">Login</a>');
	}
}

/*============ 24.Woo Login Register toggle ===========*/
function zipprich_woo_login_action(){
	var $content = $(".woocommerce").has("#customer_login").length;
	if(true == $content){
		$(".woocommerce-Button.button-register").on( 'click', function(e) {
			e.preventDefault();
			$(this).parent().slideUp();
			$('#customer_login .u-column2.col-2').slideDown();
		});
		$(".woocommerce-Button.button-login").on( 'click', function(e) {
			e.preventDefault();
			$(this).parent().slideUp();
			$('#customer_login .u-column1.col-1').slideDown();
		});
	}
}

/*============= xxxxxxxxxxxxxxxxxxxxxxxxx ===========*/

/*============= DOCUMENT READY ALL FUNCTION  CALL ===========*/
$(function(){

	if (typeof zipprich_sticky_header == 'function'){
		zipprich_sticky_header();
	}

	if (typeof zipprich_counter_up_init == 'function'){
		zipprich_counter_up_init();
	}

	if (typeof zipprich_faqaccordion_class == 'function'){
		zipprich_faqaccordion_class();
	}

	if (typeof zipprich_testimonial_carousel == 'function'){
		zipprich_testimonial_carousel();
	}

	if (typeof zipprich_clint_logos_carousel == 'function'){
		zipprich_clint_logos_carousel();
	}

	if (typeof zipprich_mobil_menu == 'function'){
		zipprich_mobil_menu();
	}
	if (typeof zipprich_video_func == 'function'){
		zipprich_video_func();
	}
	if (typeof zipprich_someOther_func == 'function'){
		zipprich_someOther_func();
	}

	if (typeof zipprich_contact_form_input == 'function'){
		zipprich_contact_form_input();
	}

	if (typeof zipprich_woocom_spinner == 'function'){
		zipprich_woocom_spinner();
	}

	if (typeof zipprich_input_chckbox_style == 'function'){
		zipprich_input_chckbox_style();
	}

	if (typeof zipprich_match_height == 'function'){
		zipprich_match_height();
	}

	if (typeof zipprich_woo_update_cart_button == 'function'){
		zipprich_woo_update_cart_button();
	}

	if (typeof zipprich_mailchimp_icon == 'function'){
		zipprich_mailchimp_icon();
	}

	if (typeof zipprich_domain_grid_layout_fix == 'function'){
		zipprich_domain_grid_layout_fix();
	}

	if (typeof zipprich_woo_lost_pass == 'function'){
		zipprich_woo_lost_pass();
	}

	if (typeof zipprich_woo_login == 'function'){
		zipprich_woo_login();
	}

	if (typeof zipprich_woo_external_product == 'function'){
		zipprich_woo_external_product();
	}

	if (typeof zipprich_mega_menu == 'function'){
		zipprich_mega_menu();
	}

	if (typeof zipprich_wcmhs_menu == 'function'){
		zipprich_wcmhs_menu();
	}

	if (typeof zipprich_wcmhs_bulkdomain == 'function'){
		zipprich_wcmhs_bulkdomain();
	}

	if (typeof zipprich_woo_search == 'function'){
		zipprich_woo_search();
	}

	if (typeof zipprich_header_search == 'function'){
		zipprich_header_search();
	}

	if (typeof zipprich_woo_login_register == 'function'){
		zipprich_woo_login_register();
	}

	if (typeof zipprich_woo_login_action == 'function'){
		zipprich_woo_login_action();
	}

});

/*============= After Woocommrece Update Cart Total ===========*/
$( document.body ).on( 'updated_cart_totals', function(){
    zipprich_woocom_spinner();
    zipprich_woo_update_cart_button();
});

})(jQuery);