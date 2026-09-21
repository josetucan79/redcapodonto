<?php


// Give link to go back to previous page if coming from a project page
$prevPageLink = "";
if (!isset($_GET['newwin']) && isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], "pid=") !== false) {
	$prevPageLink = "<div style='margin:0 0 5px;'>
						<img src='" . APP_PATH_IMAGES . "arrow_skip_180.png'>
						<a href='".htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES)."' style='color:#2E87D2;font-weight:bold;'>{$lang['help_01']}</a>
					 </div>";
}

// If site has set custom text to be displayed at top of page, then display it
$helpfaq_custom_html = '';
if (hasPrintableText($helpfaq_custom_text))
{
	// Set html for div
	$helpfaq_custom_html = "<div id='helpfaq_custom_text'><div class='blue' style='padding:10px;'>".nl2br(decode_filter_tags($helpfaq_custom_text))."</div></div>";
}

print $helpfaq_custom_html . $prevPageLink;

// Custom CSS/JS to tweak the help content that is scraped from the FAQ Builder module
?>
<style type="text/css">
	#pagecontainer {
		max-width: 1100px;
	}
    #pagecontent h3:first-child {
        color: #C00000;
    }
	.card {
		color: #333;
		border-color: #ddd;
		margin-top: 6px;
		box-shadow: 0 1px 1px rgb(0 0 0 / 5%);
        background-color: #f5f5f5 !important;
	}
	.card h4 {
		color: #333;
		background-color: #f5f5f5 !important;
		margin-top: 0;
		margin-bottom: 0;
		border-color: #ddd;
		border-bottom: 1px solid #ddd;
	}
	.card-title {
		margin: 0;
	}
	.card-body {
		padding: 0 10px 5px;
		cursor: pointer;
	}
    #pagecontent a {
		color: #337ab7;
		text-decoration: none;
	}
	.card h4 a {
		font-size: 15px;
		text-decoration: none;
		color: #333;
		outline: none;
	}
	.panel-body {
		border-top-color: #ddd;
		padding: 10px 15px 15px;
        background-color: #fff;
	}
    .panel-body a:link, .panel-body a:visited, .panel-body a:active, .panel-body a:hover {
        text-decoration: underline;
    }
    .panel a:link, .panel a:visited, .panel a:active, .panel a:hover {
        color: #333 !important;
        font-size: 15px;
    }
    select.section-dropdown {
        margin: 25px 0;
        color: #555;
    }
	.faqHeader {
		font-size: 20px;
        margin: 10px 0;
	}
	.tab-content {
		border-right: 0;
		border-left: 0;
		border-bottom: 0;
	}
    .help-block {
        display: block;
        margin-top: 5px;
        margin-bottom: 10px;
        color: #707070;
    }
    #filter-form {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 35px;
    }
    input#filter {
        font-size: 14px;
        max-width: 500px;
    }
    a.saveAndContinue {
        display: none;
    }
    .nav-tabs li.nav-item>a {
        font-size: 14px;
    }
    #pagecontent .nav-link.active {
        color: #000 !important;
    }
    #footer {
        margin-top: 50px;
    }
</style>
<?php

// Enable an auto-appearing button to allow users to scroll to top of page
outputButtonScrollToTop();

// Include help content scraped from End-User FAQ Community space
$htmlContent = file_get_contents(APP_PATH_DOCROOT . 'Help/help_content.php');
print str_replace("[redcap-version-url]", APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/", $htmlContent);

?>
<script type="text/javascript">
    // Convert the navbar
    Array.from(document.querySelectorAll('.nav-tabs li.nav-item>a')).forEach(function(item) {
        item.classList.add('nav-link');
        // Rename pane/navlink IDs so that they are not integers
        item.setAttribute('href', '#faq-pane-'+item.getAttribute('href').replace('#',''));
    });
    Array.from(document.querySelectorAll('.nav-tabs li.nav-item.active>a')).forEach(function(item) {
        item.classList.add('active');
        item.classList.add('boldish');
    });
    Array.from(document.querySelectorAll('.nav-tabs li.nav-item.active')).forEach(function(item) {
        item.classList.remove('active');
    });
    // Convert panels of content
    Array.from(document.querySelectorAll('.tab-pane')).forEach(function(item) {
        // Rename pane/navlink IDs so that they are not integers
        item.setAttribute('id', 'faq-pane-'+item.getAttribute('id'));
    });
    Array.from(document.querySelectorAll('.tab-pane.fade.active')).forEach(function(item) {
        item.classList.add('show');
    });
    Array.from(document.querySelectorAll('.panel')).forEach(function(item) {
        item.classList.add('card');
    });
    Array.from(document.querySelectorAll('.panel-heading')).forEach(function(item) {
        item.classList.add('card-title');
        item.classList.add('accordion-item');
    });
    Array.from(document.querySelectorAll('.panel-title')).forEach(function(item) {
        item.classList.add('card-body');
        item.classList.add('accordion-header');
        item.classList.remove('panel-title');
    });
    Array.from(document.querySelectorAll('.accordion-toggle')).forEach(function(item) {
        item.classList.add('accordion-button');
        item.classList.add('d-block');
        item.classList.add('pt-2');
        item.classList.add('pb-1');
        item.classList.add('px-1');
        // Add chevron
        item.innerHTML += '<div class="float-end align-middle" style="font-size:1.2em;color:#999;"><i class="fas fa-angle-down"></i></div>';
    });
    // Build sub-menu  links
    Array.from(document.querySelectorAll('.tabpanel')).forEach(function(item) {
        var itemId = item.id.replace('faq-pane-','');
        var section = document.getElementById('accordion-'+itemId);
        // Build links
        var e = document.createElement('div');
        e.classList.add('mt-3');
        e.classList.add('mb-4');
        e.innerHTML = '<span class="text-secondary">Jump to a sub-section:</span>';
        section.insertBefore(e, section.childNodes[0]);
        var sectionNum = 1;
        Array.from(document.querySelectorAll('#accordion-'+itemId+' .faqHeader')).forEach(function(item2) {
            var sectionClass = 'section-'+itemId+'-'+sectionNum;
            item2.classList.add(sectionClass);
            if (sectionNum > 1) {
                e.innerHTML += '|';
            }
            e.innerHTML += '<a href="javascript:;" class="section-link mx-2" section="'+sectionClass+'">'+item2.innerHTML+'</a>';
            sectionNum++;
        });
    });

    $(function(){
        // If click on question but not on link, then trigger link click
        $('.card-body').click(function(evt){
            if (evt.target.tagName.toLowerCase() != 'a') {
                $(this).find('a:first').trigger('click');
            }
        });
        // Navbar clicking
        $('.nav-tabs li.nav-item>a').click(function(){
            // Navbar styling
            $('.nav-tabs li.nav-item>a').removeClass('boldish').removeClass('active');
            $(this).addClass('boldish').addClass('active');
            // Hide tab pane
            $('.tab-pane.fade.active').removeClass('show').removeClass('active');
            // Show new tab pane
            var selector = $(this).attr('href');
            $(selector).addClass('show').addClass('active');
        });
        //  This function disables the enter button when searching
        $('.noEnterSubmit').keypress(function(e) {
            if (e.which == 13) e.preventDefault();
        });
        // Top margin of page
        if (getParameterByName('newwin') != '') {
            $('#pagecontainer div.container:first, #pagecontent').css({'margin-top':'0px'});
        }
        // If URL contains #hash, auto-click the tab for it
        var hash = window.location.hash.substr(1);
        re = /^([a-zA-Z0-9_-])+$/; // letters, numbers, and underscores only
        if (hash != '' && re.test(hash)) {
            $('a.nav-link[href="#faq-pane-'+hash+'"]').trigger('click');
        }
        // If URL contains 'q' parameter, then scroll to that ID in the DOM
        var qid = getParameterByName('qid');
        if (qid != '' && $("#"+qid).length) {
            $("#"+qid).addClass('show');
            $('a[href="#'+qid+'"]').removeClass('collapsed');
            setTimeout(function(){
                $('html, body').scrollTop($("#"+qid).offset().top-140);
            },200);
            setTimeout(function(){
                $("#"+qid).effect('highlight',{ },1500);
            },1200);
        } else {
            // Put focus in search box
            $('.noEnterSubmit').focus();
        }
        // Activate sub-section links to scroll to section after being selected
        $('a.section-link').click(function(){
            var sectionOb = $('.'+$(this).attr('section'));
            if (!sectionOb.length) return;
            $([document.documentElement, document.body]).scrollTop(sectionOb.offset().top-80);
            setTimeout(function(){
                sectionOb.effect('highlight',{ },2000);
            }, 600);
        });
        // Search feature
        (function($) {
            var $form = $('#filter-form');
            var $helpBlock = $("#filter-help-block");
            // Watch for user typing to refresh the filter
            $('#filter').keyup(function() {
                $('.nav-tabs .nav-link .badgerc').remove();
                var filter = $(this).val();
                $form.removeClass("has-success has-error");
                if (filter == "") {
                    $helpBlock.text("No filter applied.")
                    $('.searchable .panel').show();
                    $('.faqHeader').show();
                } else {
                    //Close any open panels
                    $('.collapse.in').removeClass('in');
                    //Hide questions, will show result later
                    $('.searchable .panel').hide();
                    var regex = new RegExp(filter, 'i');
                    var filterResult = $('.searchable .tabpanel .panel').filter(function() {
                        return regex.test($(this).text());
                    })
                    $('.faqHeader').hide();
                    if (filterResult) {
                        if (filterResult.length != 0) {
                            $form.addClass("has-success");
                            $helpBlock.text(filterResult.length + " question(s) found.");
                            filterResult.show();
                        } else {
                            $form.addClass("has-error").removeClass("has-success");
                            $helpBlock.text("No questions found.");
                        }
                    } else {
                        $form.addClass("has-error").removeClass("has-success");
                        $helpBlock.text("No questions found.");
                    }
                    // Add search match count badge in each tab
                    $('.nav-tabs .nav-link').each(function(){
                        var panel_id = $(this).attr('href');
                        var num_nonmatches = $(panel_id+' .panel.panel-default[style*="none"]').length; // Find non-matches based on style="display: none;"
                        var num_all = $(panel_id+' .panel.panel-default').length;
                        var num_matches = num_all-num_nonmatches;
                        // Add count to tab
                        if (num_matches > 0) {
                            $(this).append('<span class="badgerc">'+num_matches+'</span>');
                        }
                    });
                }
            });
        }($));
    });
</script>
