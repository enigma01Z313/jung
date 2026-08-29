<?php
require '/home/daroon/public_html/wp-load.php';
date_default_timezone_set("Asia/Tehran");
global $wpdb;
function jung_set_html_content_type(){
    return 'text/html';
}
$recieverEmail = "arman.daroon@gmail.com";
// $recieverEmail = "f.ahmadyf94@gmail.com";
$x = date("Y-m-d H:i:s");
$y = date("Y-m-d H:i:s", (time() - 60));
$z = date("Y-m-d H:i:s", (time() + 86460));
$w = date("Y-m-d H:i:s", (time() + 86400));
$rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."jung_new_staff;");
foreach($rows as $row){
    $staff_id = $row->staff_id;
    $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_appointments AS a INNER JOIN ".$wpdb->prefix."bookly_customer_appointments AS ca ON a.id = ca.appointment_id AND a.staff_id=" . $staff_id . " AND ca.status='approved' AND a.created_at<='". $x ."' AND a.created_at>'" . $y . "';");
    foreach($rows as $row){
        $id = $row->appointment_id;
        $start_date = $row->start_date;
        $customer_id = $row->customer_id;
        $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_staff WHERE id=" . $staff_id . ";");
        foreach($rows as $row){
            $full_name = $row->full_name;
        }
        $message = "<p>The therapy session is booked with <strong>" . $full_name . "</strong>.</p><p>DateTime: " . $start_date . " (Asia/Tehran)</p>";
        add_filter("wp_mail_content_type", "jung_set_html_content_type");
        $response = wp_mail($recieverEmail, "The Therapy Session", $message, "From: daroon <support@daroon.me>");

        if($response){
            $response = strip_tags($message);
        } else{
            $response = "Failed to send email.";
        }
        $wpdb->insert($wpdb->prefix."jung_notif_logs", [
            "appointment_id" => $id,
            "customer_id" => $customer_id,
            "channel" => 1,
            "type" => 4,
            "response" => $response,
            "created_at" => date("Y-m-d H:i:s")
        ], ["%d", "%d", "%d", "%d", "%s", "%s"]);
    }
    $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_appointments AS a INNER JOIN ".$wpdb->prefix."bookly_customer_appointments AS ca ON a.id = ca.appointment_id AND a.staff_id=" . $staff_id . " AND ca.status='approved' AND a.start_date<='". $z ."' AND a.start_date>'" . $w . "';");
    foreach($rows as $row){
        $id = $row->appointment_id;
        $start_date = $row->start_date;
        $customer_id = $row->customer_id;
        $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_staff WHERE id=" . $staff_id . ";");
        foreach($rows as $row){
            $full_name = $row->full_name;
        }
        $message = "<p>The therapy session is booked with <strong>" . $full_name . "</strong>.</p><p>DateTime: " . $start_date . " (Asia/Tehran)</p>";
        add_filter("wp_mail_content_type", "jung_set_html_content_type");
        $response = wp_mail($recieverEmail, "The Therapy Session", $message, "From: daroon <support@daroon.me>");

        if($response){
            $response = strip_tags($message);
        } else{
            $response = "Failed to send email.";
        }
        $wpdb->insert($wpdb->prefix."jung_notif_logs", [
            "appointment_id" => $id,
            "customer_id" => $customer_id,
            "channel" => 1,
            "type" => 4,
            "response" => $response,
            "created_at" => date("Y-m-d H:i:s")
        ], ["%d", "%d", "%d", "%d", "%s", "%s"]);
    }
}
?>