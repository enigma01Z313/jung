<?php
require '/home/daroon/public_html/wp-load.php';
require '/home/daroon/public_html/wp-content/plugins/jung/vendor/autoload.php';
date_default_timezone_set("Asia/Tehran");
global $wpdb;
$x = date("Y-m-d H:i:s", (time() - 900));
$y = date("Y-m-d H:i:s", (time() - 960));
$rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_appointments AS a INNER JOIN ".$wpdb->prefix."bookly_customer_appointments AS ca ON a.id = ca.appointment_id AND ca.status='approved' AND a.end_date<='". $x ."' AND a.end_date>'" . $y . "';");
foreach($rows as $row){
    $customer_id = $row->customer_id;
    $logs = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."jung_notif_logs WHERE channel=0 AND type=3 AND customer_id=" . $customer_id . ";");
    if(empty($logs)){
        $customers = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_customers WHERE id=" . $customer_id . " AND created_at>'2023-08-29 19:45:00';");
        foreach($customers as $customer){
            $state = true; 
            $staff_id = $row->staff_id;
            $id = $row->appointment_id;
            $start_date = new DateTime($row->start_date, new DateTimeZone('Asia/Tehran'));
            $start_date->setTimezone(new DateTimeZone($row->time_zone));
            $date = $start_date->format("j F Y");
            $phone = $customer->phone;
            if(preg_match("/^(\+98)?9\d{9}$/", $phone)) {
                $state = false;
            }
            $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."jung_blacklist;");
            foreach($rows as $row){
                if($row->phone == $phone){
                    $state = false;
                }
            }
            $rows = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."bookly_staff WHERE id=" . $staff_id . ";");
            foreach($rows as $row){
                $full_name = $row->full_name;
            }
            if($state){
                $client = new \GuzzleHttp\Client();
                $response = $client->request("POST", "https://app.trengo.com/api/v2/wa_sessions", [
                    'body' => '{"recipient_phone_number":"' . $phone . '","hsm_id":140507,"params":[{"key":"{{1}}","value":"' . $full_name .'"},{"key":"{{2}}","value":"' . $date .'"}]}',
                    'headers' => [
                        'Authorization' => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiYzFlN2E0OGQ0MDllMjgwNDMxMDc1YjkwMjFjYjNiNzdjNzE1NDAyYjQ0OGZkNGFiYWQ5NTZkOGJmYjczY2IzNTExMjY0YmI0OWQwNjYyMWQiLCJpYXQiOjE2OTA3MjEwMDcuMjkxMTcyLCJuYmYiOjE2OTA3MjEwMDcuMjkxMTc0LCJleHAiOjQ4MTQ4NTg2MDcuMjgxNTQ2LCJzdWIiOiI0NzI5NDMiLCJzY29wZXMiOltdfQ.W-JQZ_a5eDewVTgQ0VrN03vN6CYWVsXAu9u-9D0ByXOn5cJAbOgH9k4JMSzBUX_dnFg-KpxWaYT6IxV3mvVJsQ',
                        'accept' => 'application/json',
                        'content-type' => 'application/json',
                    ]
                ]);
                $wpdb->insert($wpdb->prefix."jung_notif_logs", [
                    "appointment_id" => $id,
                    "customer_id" => $customer_id,
                    "channel" => 0,
                    "type" => 3,
                    "response" => (string)$response->getBody(),
                    "created_at" => date("Y-m-d H:i:s")
                ], ["%d", "%d", "%d", "%d", "%s", "%s"]);
            }
        }
    }
}
?>