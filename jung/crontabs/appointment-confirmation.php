<?php
require '/home/daroon/public_html/wp-load.php';
date_default_timezone_set("Asia/Tehran");
global $wpdb;
function jung_set_html_content_type(){
    return 'text/html';
}
$x = date("Y-m-d H:i:s");
$y = date("Y-m-d H:i:s", (time() - 60));
$rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_appointments AS a INNER JOIN ".$wpdb->prefix."bookly_customer_appointments AS ca ON a.id = ca.appointment_id AND ca.status='approved' AND a.created_at<='". $x ."' AND a.created_at>'" . $y . "';");
foreach($rows as $row){
    $customer_id = $row->customer_id;
    $staff_id = $row->staff_id;
    $id = $row->appointment_id;
    $start_date = new DateTime($row->start_date, new DateTimeZone('Asia/Tehran'));
    $start_date->setTimezone(new DateTimeZone($row->time_zone));
    $time = $start_date->format("H:i");
    $date = $start_date->format("j F Y");
    $time_zone = $row->time_zone;
    $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_customers WHERE id=" . $customer_id . ";");
    foreach($rows as $row){
        $first_name = $row->first_name;
        $email = $row->email;
    }
    $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_staff WHERE id=" . $staff_id . ";");
    foreach($rows as $row){
        $full_name = $row->full_name;
    }
    $message = "<p><<< Your <span style='color:#FF0000'>Google Meet Link</span> will be emailed to you 24 hours before your appointment >>></p><br>
    <p><strong>Appointment Details:</strong></p>
    <p>Dear " . $first_name . ", your appointment with " . $full_name . " on " . $date . " at " . $time . " (" . $time_zone .") is confirmed.</p>
    <p>Please note the time is displayed in a 24:00 hour format. For example 8:00 means 8:00 AM and 20:00 means 8:00pm. <strong>Please double-check your confirmed time.</strong></p><br>
    <p><strong>Information for First-Time Users</strong></p>
    <i>Emergencies:</i>
    <p>Online mental wellness platforms are not designed for emergencies. If you are experiencing any of the following, please call your local emergency services in your region:</p>
    <ul>
        <li>Suicidal thoughts</li>
        <li>Having thoughts of harming others and being afraid of losing your control</li>
        <li>Being exposed to sexual or physical abuse</li>
        <li>Experiencing a manic or psychotic phase</li>
    </ul><br>
    <i>Rescheduling and Cancellation Policy</i>
    <ul>
        <li>We understand that circumstances change, and your session may need to be cancelled or rescheduled. You can make changes up to <strong>48 hours prior</strong> to your session by contacting customer support at support@daroon.me. Unfortunately, it is not possible to cancel or reschedule your appointment within 48 hours of your session. </li>
        <li>Attending your session at the designated time is essential. Postponing or extending is not possible due to overlap with subsequent patients.</li>
    </ul><br>
    <i>Required Software / App</i>
    <p>Your private sessions will be held via Google Meet. For a better user experience, we recommend connecting with a Gmail/Google account. However, this is not required. If you wish to make a Google account, please use the following link and click “create an account”: <a href='https://www.google.com/account/about/'>https://www.google.com/account/about/</a></p>";
    add_filter("wp_mail_content_type", "jung_set_html_content_type");
    $response = wp_mail($email, "Appointment Confirmation", $message, "From: daroon <support@daroon.me>");

    if($response){
        $response = '{"customer":"' . $first_name . '","date":"' . $date . '","time":"' . $time . '","timezone":"' . $time_zone . '","therapist":"' . $full_name . '"}';
    } else{
        $response = "Failed to send email.";
    }
    $wpdb->insert($wpdb->prefix."jung_notif_logs", [
        "appointment_id" => $id,
        "customer_id" => $customer_id,
        "channel" => 1,
        "type" => 0,
        "response" => $response,
        "created_at" => date("Y-m-d H:i:s")
    ], ["%d", "%d", "%d", "%d", "%s", "%s"]);      
}
?>