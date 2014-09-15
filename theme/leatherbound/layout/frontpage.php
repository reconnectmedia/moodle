<?php

$hasheading = ($PAGE->heading);
$hasnavbar = (empty($PAGE->layout_options['nonavbar']) && $PAGE->has_navbar());
$hasfooter = (empty($PAGE->layout_options['nofooter']));

$hassidepre = $PAGE->blocks->region_has_content('side-pre', $OUTPUT);
$hassidepost = $PAGE->blocks->region_has_content('side-post', $OUTPUT);

$showsidepre = ($hassidepre && !$PAGE->blocks->region_completely_docked('side-pre', $OUTPUT));
$showsidepost = ($hassidepost && !$PAGE->blocks->region_completely_docked('side-post', $OUTPUT));
$custommenu = $OUTPUT->custom_menu();
$hascustommenu = (empty($PAGE->layout_options['nocustommenu']) && !empty($custommenu));

$bodyclasses = array();
if ($showsidepre && !$showsidepost) {
    $bodyclasses[] = 'side-pre-only';
} else if ($showsidepost && !$showsidepre) {
    $bodyclasses[] = 'side-post-only';
} else if (!$showsidepost && !$showsidepre) {
    $bodyclasses[] = 'content-only';
}
if ($hascustommenu) {
    $bodyclasses[] = 'has_custom_menu';
}

echo $OUTPUT->doctype() ?>
<html <?php echo $OUTPUT->htmlattributes() ?>>
<head>
    <title><?php echo $PAGE->title ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->pix_url('favicon', 'theme')?>" />
    <meta name="description" content="<?php p(strip_tags(format_text($SITE->summary, FORMAT_HTML))) ?>" />
    <?php echo $OUTPUT->standard_head_html() ?>
      <script src="//ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.js"></script>
  <script>
 (function($){
    $.contentExpirator = function(prfx){
        var pfix = prfx || 'exp';
        $("[class|="+pfix+"]").each(function(){
            var eString = $(this).attr('class').split(' ')[0];
            var dString = eString.split('-');
            var d = new Date(dString[1],dString[2].toString()-1,dString[3]);
            var today = new Date();
            if(d < today){
                $(this).css('display','none');
            }
        });
    }
})(jQuery);</script>
 <script>
    $(document).ready(function(){
      jQuery.contentExpirator();
    });
  </script>
</head>

<body id="<?php p($PAGE->bodyid) ?>" class="<?php p($PAGE->bodyclasses.' '.join(' ', $bodyclasses)) ?>">
<?php echo $OUTPUT->standard_top_of_body_html() ?>

<div id="page">

<!-- START OF HEADER -->
    <div id="page-header">
		<div id="page-header-wrapper" class="wrapper clearfix">
	        <h1 class="headermain"><a href="/"><img src="/images/dematic-logo.jpg" /></a><?php //echo $PAGE->heading ?></h1>
    	    <div class="headermenu">
        		<?php
	        	    echo $OUTPUT->login_info();
    	        	echo $OUTPUT->lang_menu();
	        	    echo $PAGE->headingmenu;
		        ?>
	    	<br />
		        <a href="/"><img border="0" align="right" src="/images/dematicu2.png" /></a></div>
	    </div>
    </div>

<!-- END OF HEADER -->

<?php if ($hascustommenu) { ?>
<div id="custommenuwrap"><div id="custommenu"><?php echo $custommenu; ?></div></div>
<?php } ?>

<!-- START OF CONTENT -->

<div id="page-content-wrapper" class="wrapper clearfix">




    <div id="page-content">
        <div id="region-main-box">
            <div id="region-post-box">

                <div id="region-main-wrap">
                    <div id="region-main">
                        <div class="region-content">
                            <?php echo core_renderer::MAIN_CONTENT_TOKEN ?>
                        </div>
                    </div>
                </div>

                <?php if ($hassidepre) { ?>
                <div id="region-pre" class="block-region">
                    <div class="region-content">
                        <?php echo $OUTPUT->blocks_for_region('side-pre') ?>
                    </div>
                </div>
                <?php } ?>

                <?php if ($hassidepost) { ?>
                <div id="region-post" class="block-region">
                    <div class="region-content">
                        <?php echo $OUTPUT->blocks_for_region('side-post') ?>
                    </div>
                </div>
                <?php } ?>

            </div>
        </div>
    </div>
</div>

<!-- END OF CONTENT -->

<!-- START OF FOOTER -->

    <div id="page-footer" class="wrapper">
                <table align="center" border="0">
<tbody>
<tr>
<td><a href="http://www.dkdigitalmedia.com/dematic/pdf/TermsofUse.pdf">Terms of Use</a></span></td>
<td><a href="http://www.dkdigitalmedia.com/dematic/pdf/DataPrivacyProtectionPolicy.pdf">Data Privacy</a></span></td>
<td><a href="http://www.dkdigitalmedia.com/dematic/pdf/ContactUs.pdf">Contact Us</a></span></td></tr>
</tr>
</tbody>
</table>
        
        <p class="helplink">
        
        <?php echo page_doc_link(get_string('moodledocslink')) ?>
        </p>

        <?php
        echo $OUTPUT->login_info();
        //echo $OUTPUT->home_link();
        //echo $OUTPUT->standard_footer_html();
        ?>
        <br />
        <a href="/"><img src="/images/school.png" border="0" /></a>
    </div>

<!-- END OF FOOTER -->

</div>
<?php echo $OUTPUT->standard_end_of_body_html() ?>
</body>
</html>