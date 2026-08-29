<?php
require '/home/daroon/public_html/wp-load.php';
date_default_timezone_set("Asia/Tehran");
global $wpdb;
function jung_set_html_content_type(){
    return 'text/html';
}
$x = date("Y-m-d H:i:s", (time() - 10800));
$y = date("Y-m-d H:i:s", (time() - 10860));

$rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_appointments AS a INNER JOIN ".$wpdb->prefix."bookly_customer_appointments AS ca ON a.id = ca.appointment_id AND ca.status='approved' AND a.end_date<='". $x ."' AND a.end_date>'" . $y . "' AND a.service_id != 81;");
foreach($rows as $row){
    $id = $row->appointment_id;
    $customer_id = $row->customer_id;
    $staff_id = $row->staff_id;
    $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_appointments AS a INNER JOIN ".$wpdb->prefix."bookly_customer_appointments AS ca ON a.id = ca.appointment_id AND ca.status='approved' AND ca.customer_id=" . $customer_id . " AND a.staff_id=" . $staff_id . " AND a.start_date>='" . $x . "';");
    if(empty($rows)){
        $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_customers WHERE id=" . $customer_id . ";");
        foreach($rows as $row){
            $first_name = $row->first_name;
            $email = $row->email;
        }
        $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_staff WHERE id=" . $staff_id . ";");
        foreach($rows as $row){
            $full_name = $row->full_name;
        }
        $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."jung_staff_urls WHERE staff_id=" . $staff_id . ";");
        if(empty($rows)){
            $link = "https://daroon.me/appointment";
        } else{
            foreach($rows as $row){
                $link = "https://daroon.me/team/" . $row->slug;
            }
        }
        $message = "<p>Hi " . $first_name . ",</p><p>You can book your next session with " . $full_name . " using the link below. Clients who book consecutive sessions report feeling better. Keep up the good work.</p><p><a href='" . $link . "'>" . $link . "</a></p>";
        if($staff_id == 34 || $staff_id == 105){
            $message = "<p>Hi " . $first_name . ",</p><p>You can book your next session using the link below. Clients who book consecutive sessions report feeling better. Keep up the good work.</p><p><a href='https://daroon.me/appointment'>https://daroon.me/appointment</a></p>";
        }
        if($staff_id != 70 && $staff_id != 32 && $staff_id != 107 && $staff_id != 102 && $staff_id != 89){          
            add_filter("wp_mail_content_type", "jung_set_html_content_type");
            $response = wp_mail($email, "Book Your Next Session", $message, "From: daroon <support@daroon.me>");

            if($response){
                $response = strip_tags($message);
            } else{
                $response = "Failed to send email.";
            }
            $wpdb->insert($wpdb->prefix."jung_notif_logs", [
                "appointment_id" => $id,
                "customer_id" => $customer_id,
                "channel" => 1,
                "type" => 2,
                "response" => $response,
                "created_at" => date("Y-m-d H:i:s")
            ], ["%d", "%d", "%d", "%d", "%s", "%s"]);
        }     
    }
}
?>