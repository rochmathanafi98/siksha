<?php
/*
 * This file is part of shezar LMS
 *
 * Copyright (C) 2010 onwards shezar Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @deprecated since shezar 9
 * @author Brian Barnes <brian.barnes@shezarlms.com>
 * @package theme_kiwifruitresponsive
 */

if (!empty($PAGE->theme->settings->favicon)) {
    $faviconurl = $PAGE->theme->setting_file_url('favicon', 'favicon');
} else {
    $faviconurl = $OUTPUT->favicon();
}

$hassidepre = $PAGE->blocks->region_has_content('side-pre', $OUTPUT);
$hassidepost = $PAGE->blocks->region_has_content('side-post', $OUTPUT);
$hasnavbar = (empty($PAGE->layout_options['nonavbar']) && $PAGE->has_navbar());
$hasfooter = (empty($PAGE->layout_options['nofooter']));

$showsidepre = $hassidepre && !$PAGE->blocks->region_completely_docked('side-pre', $OUTPUT);
$showsidepost = $hassidepost && !$PAGE->blocks->region_completely_docked('side-post', $OUTPUT);
$showmenu = empty($PAGE->layout_options['nocustommenu']);
$sitesummary = isset($SITE->summary) ? $SITE->summary : '';
$left = (!right_to_left());

if ($showmenu) {
    // load shezar menu
    $menudata = shezar_build_menu();
    $shezar_core_renderer = $PAGE->get_renderer('shezar_core');
    $shezarmenu = $shezar_core_renderer->shezar_menu($menudata);
}

$bodyclasses = array();
if ($showsidepre && !$showsidepost) {
    if (!right_to_left()) {
        $bodyclasses[] = 'side-pre-only';
    } else {
        $bodyclasses[] = 'side-post-only';
    }
} else if ($showsidepost && !$showsidepre) {
    if (!right_to_left()) {
        $bodyclasses[] = 'side-post-only';
    } else {
        $bodyclasses[] = 'side-pre-only';
    }
} else if (!$showsidepost && !$showsidepre) {
    $bodyclasses[] = 'content-only';
}

$kiwifruitheading = $OUTPUT->kiwifruit_header();

echo $OUTPUT->doctype() ?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="generator" content="<?php echo get_string('poweredby', 'shezar_core'); ?>" />
    <meta name="description" content="<?php p(strip_tags(format_text($sitesummary, FORMAT_HTML))) ?>" />
    <link rel="shortcut icon" href="<?php echo $faviconurl; ?>" />
    <?php echo $OUTPUT->standard_head_html() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
</head>
<body <?php echo $OUTPUT->body_attributes($bodyclasses); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<?php echo $kiwifruitheading ?>
<div id="page">
  <div id="wrapper" class="clearfix">

    <?php echo $OUTPUT->full_header(); ?>

    <div id="page-content" class="row-fluid">
        <div id="region-bs-main-and-pre" class="span9 <?php echo ($left ? '' : 'pull-right'); ?>">
            <section id="region-main" class="span8 <?php echo ($left ? 'pull-right' : ''); ?>">
                <?php
                echo $OUTPUT->page_heading();
                echo $OUTPUT->blocks('main');
                echo $OUTPUT->course_content_header();
                echo $OUTPUT->main_content();
                echo $OUTPUT->course_content_footer();
                ?>
            </section>
            <?php echo $OUTPUT->blocks('side-pre', 'span4' . ($left ? ' desktop-first-column' : '')); ?>
        </div>
        <?php echo $OUTPUT->blocks('side-post', 'span3' . (!$left ? ' desktop-first-column pull-left' : '')); ?>
    </div>
  </div>
</div>

<?php if (!empty($coursefooter)) { ?>
<div id="course-footer"><?php echo $coursefooter; ?></div>
<?php } ?>

<?php if ($hasfooter) { ?>
  <div id="page-footer">
    <div class="footer-content">
      <div id="shezarmenu"><?php echo $shezarmenu; ?>
        <div class="clear"></div>
      </div>
      <div class="footer-powered"><a href="http://www.shezarlms.com/" target="_blank"><img class="logo" src="<?php echo $OUTPUT->pix_url('logo-ftr', 'theme_kiwifruitresponsive'); ?>" alt="Logo" /></a></div>
      <div class="footer-backtotop"><a href="#">Back to top</a></div>
      <div class="footnote">
        <div class="footer-links"></div>
      </div>
      <?php
      echo $OUTPUT->login_info();
      echo $OUTPUT->standard_footer_html();
      ?>
    </div>
  <div class="clear"></div>
  </div>
<?php } ?>

    <?php echo $OUTPUT->standard_end_of_body_html() ?>

</body>
</html>
