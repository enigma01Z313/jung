<?php
$root = dirname(dirname(dirname(dirname(__FILE__))));

if (file_exists($root.'/wp-load.php')) {
	include_once($root.'/wp-load.php');
}

include_once('app/reports.php');


if( !function_exists('wpdocs_set_html_mail_content_type') ){
    function wpdocs_set_html_mail_content_type() {
            return 'text/html';
    }
}


$XX = date("Y-m-d", strtotime("+2 day"));
$YY = date("Y-m-d", strtotime("+7 day"));

$fileName = schedule_evaluation_export($XX, $YY, 6000, false, 1000000);
//$fileName = schedule_evaluation_export($XX, $YY, 9, true, 1000000);

$url = "https://daroon.me/schedule_eval/$fileName";
$multiple_recipients = array();
array_push($multiple_recipients, 'supervisor@daroon.me');
array_push($multiple_recipients, 'f.ahmadyf94@gmail.com');

add_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );
wp_mail( $multiple_recipients, 'Therapists schedule evaluation', "<a href='$url'>Download File</a>");
remove_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );
