<?php
require '/home/daroon/public_html/wp-load.php';
date_default_timezone_set("Asia/Tehran");
global $wpdb;
function jung_set_html_content_type(){
    return 'text/html';
}
$x = date("Y-m-d H:i:s");
$y = date("Y-m-d H:i:s", (time() - 60));
$rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_appointments AS a INNER JOIN ".$wpdb->prefix."bookly_customer_appointments AS ca ON a.id = ca.appointment_id AND ca.status='approved' AND a.start_date<='". $x ."' AND a.start_date>'" . $y . "';");
foreach($rows as $row){
    $customer_id = $row->customer_id;
    $staff_id = $row->staff_id;
    $id = $row->appointment_id;
    $start_date = new DateTime($row->start_date, new DateTimeZone('Asia/Tehran'));
    $start_date->setTimezone(new DateTimeZone($row->time_zone));
    $time = $start_date->format("H:i");
    $date = $start_date->format("j F Y");
    $time_zone = $row->time_zone;
    $google_meet = $row->online_meeting_id;
    $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_customers WHERE id=" . $customer_id . ";");
    foreach($rows as $row){
        $first_name = $row->first_name;
        $email = $row->email;
    }
    $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_staff WHERE id=" . $staff_id . ";");
    foreach($rows as $row){
        $full_name = $row->full_name;
    }
    $message = "<p>Dear " . $first_name . ",</p>
    <p style='#FF0000'>Appointment Link: " . $google_meet . "</p>
    <p>We would like to remind you that you have an appointment at " . $time . " (" . $time_zone . ") on " . $date . " with " . $full_name .". <strong>Please note the time is displayed in 24:00 hour format.</strong></p>
    <p>Thank you,</p>
    <p>Daroon Team</p>";
    add_filter("wp_mail_content_type", "jung_set_html_content_type");
    $response = wp_mail($email, "Link + Appointment Reminder", $message, "From: daroon <support@daroon.me>");

    if($response){
        $response = '{"customer":"' . $first_name . '","meet":"' . $google_meet . '","time":"' . $time . '","timezone":"' . $timezone . '","date":"' . $date . '","therapist":"' . $full_name . '"}';
    } else{
        $response = "Failed to send email.";
    }
    $wpdb->insert($wpdb->prefix."jung_notif_logs", [
        "appointment_id" => $id,
        "customer_id" => $customer_id,
        "channel" => 1,
        "type" => 1,
        "response" => (string)$response,
        "created_at" => date("Y-m-d H:i:s")
    ], ["%d", "%d", "%d", "%d", "%s", "%s"]); 
}
?>