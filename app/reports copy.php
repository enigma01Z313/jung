<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function wa_logs()
{
    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">WhatsApp Logs</h1>
        <?php
        if (isset($_POST["filter"])) {
            $from = $_POST["from"] . " 00:00:00";
            $to = $_POST["to"] . " 23:59:59";
            $from_date = $_POST["from"];
            $to_date = $_POST["to"];
        } else {
            $from = date("Y-m-d", strtotime('-30 days')) . " 00:00:00";
            $to = date("Y-m-d") . " 23:59:59";
            $from_date = date("Y-m-d", strtotime('-30 days'));
            $to_date = date("Y-m-d");
        }
        ?>
        <table class="form-table w50">
            <tbody>
                <form method="post" action="" enctype="multipart/form-data">
                    <tr>
                        <td class="jung-control">
                            <input type="date" required name="from" style="width:100%;" value="<?php echo $from_date; ?>">
                        </td>
                        <td class="jung-control">
                            <input type="date" required name="to" style="width:100%" value="<?php echo $to_date; ?>">
                        </td>
                        <td class="jung-btn">
                            <input type="submit" class="button action" name="filter" value="Filter">
                        </td>
                    </tr>
                </form>
            </tbody>
        </table>
        <div style="overflow-x:auto">
            <table class="wp-list-table widefat striped table-view-list">
                <thead>
                    <tr>
                        <th class="manage-column">ID</th>
                        <th class="manage-column">Customer</th>
                        <th class="manage-column">Phone</th>
                        <th class="manage-column">Appointment</th>
                        <th class="manage-column">Type</th>
                        <th class="manage-column">Response</th>
                        <th class="manage-column">Created at</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $logs = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_notif_logs WHERE channel=0 AND created_at BETWEEN '" . $from . "' AND '" . $to . "' ORDER BY id DESC;");
                    foreach ($logs as $l) {
                        $customer = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customers WHERE id=" . $l->customer_id . ";");
                        foreach ($customer as $c) {
                            $full_name = $c->full_name;
                            $phone = $c->phone;
                        }
                        ?>
                        <tr>
                            <td><?php echo $l->id; ?></td>
                            <td><?php echo $full_name; ?></td>
                            <td><?php echo $phone; ?></td>
                            <td><?php echo $l->appointment_id; ?></td>
                            <?php
                            switch ($l->type) {
                                case 3:
                                    echo "<td><div class='label-type label-info'>Session Feedback</div></td>";
                                    break;
                            }
                            ?>
                            <td><?php echo $l->response; ?></td>
                            <td><?php echo $l->created_at; ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
function mail_logs()
{
    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Email Logs</h1>
        <?php
        if (isset($_POST["filter"])) {
            $from = $_POST["from"] . " 00:00:00";
            $to = $_POST["to"] . " 23:59:59";
            $from_date = $_POST["from"];
            $to_date = $_POST["to"];
        } else {
            $from = date("Y-m-d", strtotime('-30 days')) . " 00:00:00";
            $to = date("Y-m-d") . " 23:59:59";
            $from_date = date("Y-m-d", strtotime('-30 days'));
            $to_date = date("Y-m-d");
        }
        ?>
        <table class="form-table w50">
            <tbody>
                <form method="post" action="" enctype="multipart/form-data">
                    <tr>
                        <td class="jung-control">
                            <input type="date" required name="from" style="width:100%;" value="<?php echo $from_date; ?>">
                        </td>
                        <td class="jung-control">
                            <input type="date" required name="to" style="width:100%" value="<?php echo $to_date; ?>">
                        </td>
                        <td class="jung-btn">
                            <input type="submit" class="button action" name="filter" value="Filter">
                        </td>
                    </tr>
                </form>
            </tbody>
        </table>
        <div style="overflow-x:auto">
            <table class="wp-list-table widefat striped table-view-list">
                <thead>
                    <tr>
                        <th class="manage-column">ID</th>
                        <th class="manage-column">Customer</th>
                        <th class="manage-column">Email</th>
                        <th class="manage-column">Appointment</th>
                        <th class="manage-column">Type</th>
                        <th class="manage-column">Response</th>
                        <th class="manage-column">Created at</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $logs = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_notif_logs WHERE channel=1 AND created_at BETWEEN '" . $from . "' AND '" . $to . "' ORDER BY id DESC;");
                    foreach ($logs as $l) {
                        $customer = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customers WHERE id=" . $l->customer_id . ";");
                        foreach ($customer as $c) {
                            $full_name = $c->full_name;
                            $email = $c->email;
                        }
                        ?>
                        <tr>
                            <td><?php echo $l->id; ?></td>
                            <td><?php echo $full_name; ?></td>
                            <td><?php echo $email; ?></td>
                            <td><?php echo $l->appointment_id; ?></td>
                            <?php
                            switch ($l->type) {
                                case 0:
                                    echo "<td><div class='label-type label-default'>Booking Confirmation</div></td>";
                                    break;
                                case 2:
                                    echo "<td><div class='label-type label-success'>Reminder to Book Next Session</div></td>";
                                    break;
                                case 4:
                                    echo "<td><div class='label-type label-warning'>First Session</div></td>";
                                    break;
                            }
                            ?>
                            <td><?php echo $l->response; ?></td>
                            <td><?php echo $l->created_at; ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
function calc_0($x, $y)
{
    $H = intval(substr($y, 0, 2));
    $total = 0;
    $state = true;
    if ($H < 24) {
        while ($state) {
            $x = date("H:i:s", strtotime($x . " +5 minutes"));
            $total += 5;
            if ($x == $y) {
                $state = false;
            }
        }
    } else if ($H == 24) {
        while ($state) {
            $x = date("H:i:s", strtotime($x . " +5 minutes"));
            $total += 5;
            if ($x == "00:00:00") {
                $state = false;
            }
        }
        $total += intval(substr($y, 2, 4));
    } else if ($H > 24) {
        while ($state) {
            $x = date("H:i:s", strtotime($x . " +5 minutes"));
            $total += 5;
            if ($x == "00:00:00") {
                $state = false;
            }
        }
        $state = true;
        $x = "00:00:00";
        if (($H - 24) < 10) {
            $y = "0" . strval($H - 24) . substr($y, 2, 6);
        } else {
            $y = strval($H - 24) . substr($y, 2, 6);
        }
        while ($state) {
            $x = date("H:i:s", strtotime($x . " +5 minutes"));
            $total += 5;
            if ($x == $y) {
                $state = false;
            }
        }

    }
    return $total;
}
function calc_1($y)
{
    $H = intval(substr($y, 0, 2));
    $total = 0;
    if ($H > 24) {
        if (($H - 24) < 10) {
            $y = "0" . strval($H - 24) . substr($y, 2, 6);
        } else {
            $y = strval($H - 24) . substr($y, 2, 6);
        }
        $x = "00:00:00";
        $state = true;
        while ($state) {
            $x = date("H:i:s", strtotime($x . " +5 minutes"));
            $total += 5;
            if ($x == $y) {
                $state = false;
            }
        }
    } else if ($H == 24) {
        $total += intval(substr($y, 2, 4));
    }
    return $total;
}
function calc_2($x, $y, $staff_id)
{
    global $wpdb;
    $tmp = array();
    array_push($tmp, array("date" => date("Y-m-d", strtotime($x . ' -1 days')), "day_index" => date("N", strtotime($x)), "total" => 0));
    array_push($tmp, array("date" => $y, "day_index" => date("N", strtotime($y . " +1 days")), "total" => 0));
    for ($i = 0; $i < count($tmp); $i++) {
        $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_staff_schedule_items WHERE staff_id=" . $staff_id . " AND day_index=" . $tmp[$i]["day_index"] . ";");
        foreach ($rows as $row) {
            $tmp[$i]["total"] += calc_1($row->end_time);
            $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_schedule_item_breaks WHERE staff_schedule_item_id=" . $row->id . ";");
            $break = 0;
            foreach ($rows as $row) {
                $break += calc_1($row->end_time);
            }
            $tmp[$i]["total"] -= $break;
        }
    }
    for ($i = 0; $i < count($tmp); $i++) {
        $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_staff_special_days WHERE staff_id=" . $staff_id . ";");
        foreach ($rows as $row) {
            if ($tmp[$i]["date"] == $row->date) {
                $tmp[$i]["total"] += calc_1($row->end_time);
                $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_special_days_breaks WHERE staff_special_day_id=" . $row->id . ";");
                $break = 0;
                foreach ($rows as $row) {
                    $break += calc_1($row->end_time);
                }
                $tmp[$i]["total"] -= $break;
            }
        }
    }
    return $tmp;
}
function reports_0()
{
    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Evaluation Schedule</h1>
        <?php
        if (isset($_POST["filter"])) {
            $from = $_POST["from"] . " 00:00:00";
            $to = $_POST["to"] . " 23:59:59";
            $from_date = $_POST["from"];
            $to_date = $_POST["to"];
        } else {
            $from = date("Y-m-d", strtotime('-30 days')) . " 00:00:00";
            $to = date("Y-m-d") . " 23:59:59";
            $from_date = date("Y-m-d", strtotime('-30 days'));
            $to_date = date("Y-m-d");
        }
        if (isset($_POST["export"])) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue("A1", "نام درمانگر");
            $sheet->setCellValue("B1", "تعداد هفته کارکرد");
            $sheet->setCellValue("C1", "تعداد کل مراجعان");
            $sheet->setCellValue("D1", "تعداد جلسات رزرو شده");
            $sheet->setCellValue("E1", "تعداد مراجعین جدید پیش‌رو");
            $sheet->setCellValue("F1", "تعداد جلسات رزرو شده پیش‌رو");
            $sheet->setCellValue("G1", "تعداد کل ریزش");
            $sheet->setCellValue("H1", "تعداد ریزش در سه جلسه اول");
            $sheet->setCellValue("I1", "تعداد ریزش در جلسه اول");
            $sheet->setCellValue("J1", "تعداد ریزش در جلسه دوم");
            $sheet->setCellValue("K1", "تعداد مراجع پایان درمان");
            $sheet->setCellValue("L1", "تعداد مراجعان تاکنون");
            $sheet->setCellValue("M1", "درصد کل ریزش");
            $sheet->setCellValue("N1", "درصد ریزش سه جلسه اول");
            $sheet->setCellValue("O1", "درصد ریزش بعد از سه جلسه");
            $sheet->setCellValue("P1", "درصد ریزش نسبت به ریزش کل سیستم");
            $sheet->setCellValue("Q1", "میانگین تعداد جلسات هر درمانگر");
            $sheet->setCellValue("R1", "تعداد جلسات تقسیم بر هفته کارکرد");
            $sheet->setCellValue("S1", "میانگین جلسات مراجعین با حذف سه جلسه اول");
            $sheet->setCellValue("T1", "#");
            $sheet->setCellValue("U1", "تعداد وقت‌های رزرو شده");
            $sheet->setCellValue("V1", "تعداد کل وقت‌ها");
            $sheet->setCellValue("W1", "تعداد وقت‌های خالی");
            $sheet->setCellValue("X1", "درصد وقت‌های رزرو شده");
            $sheet->setCellValue("Y1", "درصد وقت‌های خالی");
            $sheet->setCellValue("Z1", "From " . $_POST["x"] . " to " . $_POST["y"]);
            $now = date("Y-m-d H:i:s");
            $fourteen_days_ago = date("Y-m-d H:i:s", strtotime('-14 days'));
            $thirty_days_ago = date("Y-m-d H:i:s", strtotime('-30 days'));
            $from_date = $_POST["x"] . " 00:00:00";
            $to_date = $_POST["y"] . " 23:59:59";
            $is_active = false;
            $row_number = 2;
            $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_staff ORDER BY id ASC;");
            foreach ($rows as $row) {
                $staff_id = $row->id;
                $A = $row->full_name;
                $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_appointments WHERE staff_id=" . $staff_id . " ORDER BY start_date ASC;");
                $first_session = "";
                foreach ($rows as $row) {
                    $appointment_id = $row->id;
                    $first_session = $row->start_date;
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customer_appointments WHERE appointment_id=" . $appointment_id . " AND status='approved';");
                    if (!empty($rows)) {
                        break;
                    }
                }
                $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_appointments WHERE staff_id=" . $staff_id . " ORDER BY start_date DESC;");
                $last_session = "";
                foreach ($rows as $row) {
                    $appointment_id = $row->id;
                    $last_session = $row->start_date;
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customer_appointments WHERE appointment_id=" . $appointment_id . " AND status='approved';");
                    if (!empty($rows)) {
                        break;
                    }
                }
                if ($last_session > $thirty_days_ago) {
                    $is_active = true;
                }
                if ($is_active) {
                    $first_session = new DateTime($first_session);
                    $today = new DateTime($now);
                    $diff = $today->diff($first_session);
                    $B = floor(($diff->days + 1) / 7);
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customer_appointments AS ca INNER JOIN " . $wpdb->prefix . "bookly_appointments AS a ON ca.appointment_id = a.id AND a.staff_id=" . $staff_id . " AND ca.status='approved';");
                    $users = array();
                    foreach ($rows as $row) {
                        array_push($users, $row->customer_id);
                    }
                    $D = count($rows);
                    $users = array_unique($users);
                    $G = 0;
                    $H = 0;
                    $preH = 0;
                    $I = 0;
                    $J = 0;
                    $K = 0;
                    foreach ($users as $value) {
                        $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customer_appointments AS ca INNER JOIN " . $wpdb->prefix . "bookly_appointments AS a ON ca.appointment_id = a.id AND ca.customer_id=" . $value . " AND a.staff_id=" . $staff_id . " AND ca.status='approved' ORDER BY ca.id DESC LIMIT 1;");
                        foreach ($rows as $row) {
                            if ($row->start_date < $fourteen_days_ago) {
                                $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customer_appointments AS ca INNER JOIN " . $wpdb->prefix . "bookly_appointments AS a ON ca.appointment_id = a.id AND ca.customer_id=" . $value . " AND a.staff_id=" . $staff_id . " AND ca.status='approved';");
                                $counter = 0;
                                foreach ($rows as $row) {
                                    $counter++;
                                }
                                if ($counter < 8 && $counter > 0) {
                                    $G++;
                                }
                                if ($counter <= 3 && $counter > 0) {
                                    $H++;
                                }
                                if ($counter == 1) {
                                    $I++;
                                }
                                if ($counter == 2) {
                                    $J++;
                                }
                                if ($counter == 3) {
                                    $preH++;
                                }
                                if ($counter >= 8) {
                                    $K++;
                                }
                            }
                        }
                        // $H = $I + $J + $preH;              
                    }
                    $C = count($users);
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customer_appointments AS ca INNER JOIN " . $wpdb->prefix . "bookly_appointments AS a ON ca.appointment_id = a.id AND a.staff_id=" . $staff_id . " AND a.start_date>'" . $now . "' AND ca.status='approved';");
                    $users = array();
                    foreach ($rows as $row) {
                        array_push($users, $row->customer_id);
                    }
                    $F = count($users);
                    $new_users = array();
                    $users = array_unique($users);
                    foreach ($users as $value) {
                        $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customer_appointments AS ca INNER JOIN " . $wpdb->prefix . "bookly_appointments AS a ON ca.appointment_id = a.id AND ca.customer_id=" . $value . " AND a.staff_id=" . $staff_id . " AND a.start_date<'" . $now . "' AND ca.status='approved';");
                        if (empty($rows)) {
                            array_push($new_users, $value);
                        }
                    }
                    $E = count($new_users);
                    try {
                        $L = $C - $E;
                    } catch (DivisionByZeroError $e) {
                        $L = 0;
                    }
                    try {
                        $M = number_format(100 * $G / $L, "2");
                    } catch (DivisionByZeroError $e) {
                        $M = 0;
                    }
                    try {
                        $N = number_format(100 * $H / $G, "2");
                    } catch (DivisionByZeroError $e) {
                        $N = 0;
                    }
                    try {
                        $O = 100 - $N;
                    } catch (DivisionByZeroError $e) {
                        $O = 0;
                    }
                    try {
                        $P = 0;
                    } catch (DivisionByZeroError $e) {
                        $P = 0;
                    }
                    try {
                        $Q = number_format($D / $C, "2");
                    } catch (DivisionByZeroError $e) {
                        $Q = 0;
                    }
                    try {
                        $R = number_format($Q / $B, "2");
                    } catch (DivisionByZeroError $e) {
                        $R = 0;
                    }
                    try {
                        $S = number_format((($D) - ($I + $J * 2 + ($H - $I - $J) * 3)) / ($C - $H), "2");
                    } catch (DivisionByZeroError $e) {
                        $S = 0;
                    }
                    try {
                        $T = number_format($S / $B, "2");
                    } catch (DivisionByZeroError $e) {
                        $T = 0;
                    }
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_appointments AS a INNER JOIN " . $wpdb->prefix . "bookly_customer_appointments AS ca ON a.id = ca.appointment_id AND a.staff_id=" . $staff_id . " AND a.start_date BETWEEN'" . $from_date . "' AND '" . $to_date . "' AND ca.status='approved';");
                    $reserved = count($rows);
                    $U = $reserved;
                    $state = true;
                    $x = $_POST["x"];
                    $schedule = array();
                    while ($state) {
                        array_push($schedule, array("date" => $x, "day_index" => date("N", strtotime($x . " +1 days")), "total" => 0));
                        $x = date("Y-m-d", strtotime($x . " +1 days"));
                        if ($x > $_POST["y"]) {
                            $state = false;
                        }
                    }
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_staff_schedule_items WHERE staff_id=" . $staff_id . " ORDER BY day_index ASC;");
                    foreach ($rows as $row) {
                        $id = $row->id;
                        $day_index = $row->day_index;
                        if ($row->start_time != null) {
                            $total = calc_0($row->start_time, $row->end_time);
                        } else {
                            $total = 0;
                        }
                        $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_schedule_item_breaks WHERE staff_schedule_item_id=" . $id . ";");
                        $break = 0;
                        foreach ($rows as $row) {
                            $break += calc_0($row->start_time, $row->end_time);
                        }
                        $total -= $break;
                        for ($i = 0; $i < count($schedule); $i++) {
                            if ($schedule[$i]["day_index"] == $day_index) {
                                $schedule[$i]["total"] += $total;
                            }
                        }
                    }
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_staff_special_days WHERE staff_id=" . $staff_id . ";");
                    foreach ($rows as $row) {
                        $id = $row->id;
                        $date = $row->date;
                        $x = $row->start_time;
                        $y = $row->end_time;
                        for ($i = 0; $i < count($schedule); $i++) {
                            if ($schedule[$i]["date"] == $date) {
                                $total = calc_0($x, $y);
                                $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_special_days_breaks WHERE staff_special_day_id=" . $id . ";");
                                $break = 0;
                                foreach ($rows as $row) {
                                    $break += calc_0($row->start_time, $row->end_time);
                                }
                                $total -= $break;
                                $schedule[$i]["total"] += $total;
                            }
                        }
                    }
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_holidays WHERE staff_id=" . $staff_id . ";");
                    foreach ($rows as $row) {
                        $date = $row->date;
                        for ($i = 0; $i < count($schedule); $i++) {
                            if ($schedule[$i]["date"] == $date) {
                                $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_staff_schedule_items WHERE staff_id=" . $staff_id . " AND day_index=" . $schedule[$i]["day_index"] . ";");
                                foreach ($rows as $row) {
                                    $x = $row->start_time;
                                    $y = $row->end_time;
                                    $h = intval(substr($y, 0, 2));
                                    if ($h > 24) {
                                        $total = 0;
                                        $state = true;
                                        while ($state) {
                                            $x = date("H:i:s", strtotime($x . " +5 minutes"));
                                            $total += 5;
                                            if ($x == "00:00:00") {
                                                $state = false;
                                            }
                                        }
                                        $schedule[$i]["total"] -= $total;
                                    } else if ($h == 24) {
                                        $schedule[$i]["total"] = intval(substr($y, 2, 4));
                                    } else {
                                        $schedule[$i]["total"] = 0;
                                    }
                                }
                            }
                        }
                    }
                    $tmp = calc_2($schedule[0]["date"], $schedule[count($schedule) - 1]["date"], $staff_id);
                    $schedule[0]["total"] += $tmp[0]["total"];
                    $schedule[count($schedule) - 1]["total"] -= $tmp[1]["total"];
                    $total = 0;
                    for ($i = 0; $i < count($schedule); $i++) {
                        $total += $schedule[$i]["total"];
                    }
                    $total = round($total / 60);
                    $V = $total;
                    $W = $total - $reserved;
                    if ($W < 0) {
                        $W = 0;
                    }
                    try {
                        $X = round(($reserved * 100) / $total);
                    } catch (DivisionByZeroError $e) {
                        $X = 0;
                    }
                    try {
                        $Y = round(($W * 100) / $total);
                    } catch (DivisionByZeroError $e) {
                        $Y = 0;
                    }
                    $row = array(
                        "A" => $A,
                        "B" => $B,
                        "C" => $C,
                        "D" => $D,
                        "E" => $E,
                        "F" => $F,
                        "G" => $G,
                        "H" => $H,
                        "I" => $I,
                        "J" => $J,
                        "K" => $K,
                        "L" => $L,
                        "M" => $M,
                        "N" => $N,
                        "O" => $O,
                        "P" => $P,
                        "Q" => $Q,
                        "R" => $R,
                        "S" => $S,
                        "T" => $T,
                        "U" => $U,
                        "V" => $V,
                        "W" => $W,
                        "X" => $X,
                        "Y" => $Y
                    );
                    foreach ($row as $key => $value) {
                        $sheet->setCellValue($key . $row_number, $value);
                    }
                    $is_active = false;
                    $row_number++;
                }
            }
            $path = "/home/daroon/domains/daroon.me/public_html/wp-content/uploads/jung_files/";
            $file_name = "evaluation_schedule_" . date("Y-m-d-H-i-s") . ".xlsx";
            $writer = new Xlsx($spreadsheet);
            $writer->save($path . $file_name);
            $wpdb->insert($wpdb->prefix . "jung_files", [
                "name" => $file_name,
                "type" => 0,
                "created_at" => date("Y-m-d H:i:s")
            ], ["%s", "%d", "%s"]);
            echo '<div class="notice notice-success"> 
                <p><strong>Successfully completed.</strong></p>
            </div>';
        }
        if (isset($_POST["delete"])) {
            $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_files WHERE id=" . $_COOKIE["jung_file_id"] . ";");
            foreach ($rows as $row) {
                $name = $row->name;
            }
            unlink(WP_CONTENT_DIR . "/uploads/jung_files/" . $name);
            if ($wpdb->delete($wpdb->prefix . "jung_files", ["id" => (int) $_COOKIE["jung_file_id"]], ["%d"]) == 1) {
                echo '<div class="notice notice-success"> 
                    <p><strong>Deleted successfully.</strong></p>
                </div>';
            } else {
                echo '<div class="notice notice-error"> 
                    <p><strong>Error! please try again later.</strong></p>
                </div>';
            }
        }
        ?>
        <table class="form-table w50">
            <tbody>
                <form method="post" action="" enctype="multipart/form-data">
                    <tr>
                        <td class="jung-control">
                            <input type="date" required name="x" style="width:100%;" value="">
                        </td>
                        <td class="jung-control">
                            <input type="date" required name="y" style="width:100%" value="">
                        </td>
                        <td class="jung-btn">
                            <input type="submit" class="button button-primary" name="export" value="Export">
                        </td>
                    </tr>
                </form>
                <form method="post" action="" enctype="multipart/form-data">
                    <tr>
                        <td class="jung-control">
                            <input type="date" required name="from" style="width:100%;" value="<?php echo $from_date; ?>">
                        </td>
                        <td class="jung-control">
                            <input type="date" required name="to" style="width:100%" value="<?php echo $to_date; ?>">
                        </td>
                        <td class="jung-btn">
                            <input type="submit" class="button action" name="filter" value="Filter">
                        </td>
                    </tr>
                </form>
            </tbody>
        </table>
        <div style="overflow-x:auto">
            <table class="wp-list-table widefat striped table-view-list">
                <thead>
                    <tr>
                        <th class="manage-column">ID</th>
                        <th class="manage-column">Name</th>
                        <th class="manage-column">Created at</th>
                        <th class="manage-column">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_files WHERE created_at BETWEEN '" . $from . "' AND '" . $to . "' AND type=0 ORDER BY id DESC;");
                    foreach ($rows as $row) {
                        ?>
                        <tr>
                            <td><?php echo $row->id; ?></td>
                            <td><?php echo $row->name; ?></td>
                            <td><?php echo $row->created_at; ?></td>
                            <td>
                                <div class="flex-container">
                                    <a class="button action" target="_blank"
                                        href="<?php echo get_site_url() . "/wp-content/uploads/jung_files/" . $row->name; ?>">Download</a>
                                    <button class="button action"
                                        onclick="getConfirmation(<?php echo $row->id; ?>)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div id="modal" class="modal">
            <div class="modal-content">
                <h2>Are you sure?</h2>
                <p>Do you really want to delete this record? This process can't be undone.</p>
                <br />
                <div class="flex-container">
                    <button class="button button-primary" id="cancel">Cancel</button>
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="submit" class="button button-primary" name="delete" value="Delete">
                    </form>
                </div>
            </div>
        </div>
        <script>
            var modal = document.getElementById("modal");
            function getConfirmation(file_id) {
                modal.style.display = "block";
                document.cookie = "jung_file_id=" + file_id;
            }
            var cancel = document.getElementById("cancel");
            cancel.onclick = function () {
                modal.style.display = "none";
            }
        </script>
    </div>
    <?php
}
function reports_1()
{

    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Customers Report</h1>
        <?php
        if (isset($_POST["filter"])) {
            $from = $_POST["from"] . " 00:00:00";
            $to = $_POST["to"] . " 23:59:59";
            $from_date = $_POST["from"];
            $to_date = $_POST["to"];
        } else {
            $from = date("Y-m-d", strtotime('-30 days')) . " 00:00:00";
            $to = date("Y-m-d") . " 23:59:59";
            $from_date = date("Y-m-d", strtotime('-30 days'));
            $to_date = date("Y-m-d");
        }
        if (isset($_POST["export"])) {
            set_time_limit(0);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $counter_0 = 1;
            $customers = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customers  ORDER BY id ASC ;");

            $staffArray = [];
            foreach ($customers as $c) {
                $cFullName = $c->full_name;
                $cEmail = $c->email;
                $cPhone = $c->phone;
                $sheet->setCellValue("B" . $counter_0, $cFullName);
                $sheet->setCellValue("C" . $counter_0, $cEmail);
                $sheet->setCellValue("D" . $counter_0, $cPhone);
                $counter_1 = $counter_0;

                $rows = $wpdb->get_results("SELECT
                    a.staff_id,count(*) as total ,
	                MAX(a.start_date) as start_date
                FROM
                   " . $wpdb->prefix . "bookly_customer_appointments AS ca
                    INNER JOIN " . $wpdb->prefix . "bookly_appointments AS a ON ca.appointment_id = a.id 
                    AND ca.customer_id = " . $c->id . " 
                    AND ca.status = 'approved' 
                    group by a.staff_id ; ");

                if ($rows) {
                    foreach ($rows as $s) {
                        if (!$staffArray[$s->staff_id]) {
                            $staff = $wpdb->get_row("select * from " . $wpdb->prefix . "bookly_staff where id=" . $s->staff_id);
                            $staffArray[$s->staff_id] = $staff;
                        } else {
                            $staff = $staffArray[$s->staff_id];
                        }
                        $sheet->setCellValue("A" . $counter_1, $c->id);
                        $sheet->setCellValue("B" . $counter_1, $cFullName);
                        $sheet->setCellValue("C" . $counter_1, $cEmail);
                        $sheet->setCellValue("D" . $counter_1, $cPhone);
                        $sheet->setCellValue("E" . $counter_1, $staff->full_name);
                        $sheet->setCellValue("F" . $counter_1, $s->total);
                        // $last = $wpdb->get_var("SELECT MAX(start_date) FROM " . $wpdb->prefix . "bookly_customer_appointments AS ca INNER JOIN " . $wpdb->prefix . "bookly_appointments AS a ON ca.appointment_id = a.id AND a.staff_id=" . $s->staff_id . " AND ca.customer_id=" . $c->id . " AND ca.status='approved';");
                        $sheet->setCellValue("G" . $counter_1, $s->start_date);
                        $counter_1++;
                    }
                }

                $counter_0 = $counter_1;
            }
            $path = WP_CONTENT_DIR . "/uploads/jung_files/";
            $file_name = "customers_report_" . date("Y-m-d-H-i-s") . ".xlsx";
            $writer = new Xlsx($spreadsheet);
            $writer->save($path . $file_name);
            $wpdb->insert($wpdb->prefix . "jung_files", [
                "name" => $file_name,
                "type" => 2,
                "created_at" => date("Y-m-d H:i:s")
            ], ["%s", "%d", "%s"]);
            echo '<div class="notice notice-success"> 
                <p><strong>Successfully completed.</strong></p>
            </div>';
        }
        if (isset($_POST["delete"])) {
            $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_files WHERE id=" . $_COOKIE["jung_file_id"] . ";");
            foreach ($rows as $row) {
                $name = $row->name;
            }
            unlink(WP_CONTENT_DIR . "/uploads/jung_files/" . $name);
            if ($wpdb->delete($wpdb->prefix . "jung_files", ["id" => (int) $_COOKIE["jung_file_id"]], ["%d"]) == 1) {
                echo '<div class="notice notice-success"> 
                    <p><strong>Deleted successfully.</strong></p>
                </div>';
            } else {
                echo '<div class="notice notice-error"> 
                    <p><strong>Error! please try again later.</strong></p>
                </div>';
            }
        }
        ?>
        <table class="form-table w50">
            <tbody>
                <form method="post" action="" enctype="multipart/form-data">
                    <tr>
                        <td class="jung-btn">
                            <input type="submit" class="button button-primary" name="export" value="Export">
                        </td>
                    </tr>
                </form>
                <form method="post" action="" enctype="multipart/form-data">
                    <tr>
                        <td class="jung-control">
                            <input type="date" required name="from" style="width:100%;" value="<?php echo $from_date; ?>">
                        </td>
                        <td class="jung-control">
                            <input type="date" required name="to" style="width:100%" value="<?php echo $to_date; ?>">
                        </td>
                        <td class="jung-btn">
                            <input type="submit" class="button action" name="filter" value="Filter">
                        </td>
                    </tr>
                </form>
            </tbody>
        </table>
        <div style="overflow-x:auto">
            <table class="wp-list-table widefat striped table-view-list">
                <thead>
                    <tr>
                        <th class="manage-column">ID</th>
                        <th class="manage-column">Name</th>
                        <th class="manage-column">Created at</th>
                        <th class="manage-column">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_files WHERE created_at BETWEEN '" . $from . "' AND '" . $to . "' AND type=2 ORDER BY id DESC;");
                    foreach ($rows as $row) {
                        ?>
                        <tr>
                            <td><?php echo $row->id; ?></td>
                            <td><?php echo $row->name; ?></td>
                            <td><?php echo $row->created_at; ?></td>
                            <td>
                                <div class="flex-container">
                                    <a class="button action" target="_blank"
                                        href="<?php echo get_site_url() . "/wp-content/uploads/jung_files/" . $row->name; ?>">Download</a>
                                    <button class="button action"
                                        onclick="getConfirmation(<?php echo $row->id; ?>)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div id="modal" class="modal">
            <div class="modal-content">
                <h2>Are you sure?</h2>
                <p>Do you really want to delete this record? This process can't be undone.</p>
                <br />
                <div class="flex-container">
                    <button class="button button-primary" id="cancel">Cancel</button>
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="submit" class="button button-primary" name="delete" value="Delete">
                    </form>
                </div>
            </div>
        </div>
        <script>
            var modal = document.getElementById("modal");
            function getConfirmation(file_id) {
                modal.style.display = "block";
                document.cookie = "jung_file_id=" + file_id;
            }
            var cancel = document.getElementById("cancel");
            cancel.onclick = function () {
                modal.style.display = "none";
            }
        </script>
    </div>
    <?php
}
function staff_urls()
{
    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Therapists URL</h1>
        <?php
        if (isset($_POST["submit"])) {
            if (
                $wpdb->insert($wpdb->prefix . "jung_staff_urls", [
                    "staff_id" => $_POST["staff"],
                    "slug" => $_POST["slug"],
                ], ["%d", "%s"]) != ""
            ) {
                echo '<div class="notice notice-success"> 
                        <p><strong>Submitted successfully.</strong></p>
                    </div>';
            } else {
                echo '<div class="notice notice-error"> 
                        <p><strong>Error! submit failed.</strong></p>
                    </div>';
            }
        }
        if (isset($_POST["delete"])) {
            if ($wpdb->delete($wpdb->prefix . "jung_staff_urls", ["staff_id" => (int) $_COOKIE["jung_staff_id"]], ["%d"]) == 1) {
                echo '<div class="notice notice-success"> 
                    <p><strong>Deleted successfully.</strong></p>
                </div>';
            } else {
                echo '<div class="notice notice-error"> 
                        <p><strong>Error! please try again later.</strong></p>
                    </div>';
            }
        }
        ?>
        <div class="card">
            <div class="card-body">
                <table class="form-table">
                    <tbody>
                        <form method="post" action="" enctype="multipart/form-data">
                            <tr>
                                <td class="label">
                                    <label>Therapist</label>
                                </td>
                                <td class="input">
                                    <select required name="staff" style="width: 100%;">
                                        <option value="">Select</option>
                                        <?php
                                        $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_staff ORDER BY id DESC;");
                                        foreach ($rows as $row) {
                                            ?>
                                            <option value="<?php echo $row->id; ?>"><?php echo $row->full_name; ?></option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td class="label">
                                    <label>Slug</label>
                                </td>
                                <td class="input">
                                    <input type="text" required name="slug">
                                </td>
                                <td>
                                    <input type="submit" class="button button-primary" name="submit" value="Add">
                                </td>
                                <td></td>
                            </tr>
                        </form>
                    </tbody>
                </table>
            </div>
        </div>
        <br />
        <hr />
        <br />
        <div style="overflow-x:auto">
            <table class="wp-list-table widefat striped table-view-list">
                <thead>
                    <tr>
                        <th class="manage-column">Row</th>
                        <th class="manage-column">Name</th>
                        <th class="manage-column">Slug</th>
                        <th class="manage-column">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $staff_urls = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_staff_urls ORDER BY staff_id DESC;");
                    $counter = 1;
                    foreach ($staff_urls as $su) {
                        $staff = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_staff WHERE id=" . $su->staff_id . ";");
                        foreach ($staff as $s) {
                            $staff_name = $s->full_name;
                        }
                        ?>
                        <tr>
                            <td><?php echo $counter; ?></td>
                            <td><?php echo $staff_name; ?></td>
                            <td><a href="https://daroon.me/team/<?php echo $su->slug; ?>"
                                    target="_blank"><?php echo $su->slug; ?></a></td>
                            <td>
                                <button class="button action"
                                    onclick="getConfirmation(<?php echo $su->staff_id; ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php
                        $counter++;
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div id="modal" class="modal">
            <div class="modal-content">
                <h2>Are you sure?</h2>
                <p>Do you really want to delete this record? This process can't be undone.</p>
                <br />
                <div class="flex-container">
                    <button class="button button-primary" id="cancel">Cancel</button>
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="submit" class="button button-primary" name="delete" value="Delete">
                    </form>
                </div>
            </div>
        </div>
        <script>
            var modal = document.getElementById("modal");
            function getConfirmation(staff_id) {
                modal.style.display = "block";
                document.cookie = "jung_staff_id=" + staff_id;
            }
            var cancel = document.getElementById("cancel");
            cancel.onclick = function () {
                modal.style.display = "none";
            }
        </script>
    </div>
    <?php
}
function new_staff()
{
    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">New Therapists</h1>
        <?php
        if (isset($_POST["submit"])) {
            $result = $wpdb->insert(
                $wpdb->prefix . "jung_new_staff",
                array(
                    "staff_id" => $_POST["staff"],
                    "created_at" => date("Y-m-d H:i:s")
                )
            );

            if ($result != "") {
                echo '<div class="notice notice-success"> 
                        <p><strong>Submitted successfully.</strong></p>
                    </div>';
            } else {
                echo '<div class="notice notice-error"> 
                        <p><strong>Error! submit failed.</strong></p>
                    </div>';
            }
        }
        if (isset($_POST["delete"])) {
            if ($wpdb->delete($wpdb->prefix . "jung_new_staff", ["staff_id" => (int) $_COOKIE["jung_staff_id"]], ["%d"]) == 1) {
                echo '<div class="notice notice-success"> 
                    <p><strong>Deleted successfully.</strong></p>
                </div>';
            } else {
                echo '<div class="notice notice-error"> 
                        <p><strong>Error! please try again later.</strong></p>
                    </div>';
            }
        }
        ?>
        <div class="card">
            <div class="card-body">
                <table class="form-table">
                    <tbody>
                        <form method="post" action="" enctype="multipart/form-data">
                            <tr>
                                <td class="label">
                                    <label>Therapist</label>
                                </td>
                                <td class="input">
                                    <select required name="staff" style="width: 100%;">
                                        <option value="">Select</option>
                                        <?php
                                        $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_staff ORDER BY id DESC;");
                                        foreach ($rows as $row) {
                                            ?>
                                            <option value="<?php echo $row->id; ?>"><?php echo $row->full_name; ?></option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="submit" class="button button-primary" name="submit" value="Add">
                                </td>
                                <td></td>
                            </tr>
                        </form>
                    </tbody>
                </table>
            </div>
        </div>
        <br />
        <hr />
        <br />
        <div style="overflow-x:auto">
            <table class="wp-list-table widefat striped table-view-list">
                <thead>
                    <tr>
                        <th class="manage-column">Row</th>
                        <th class="manage-column">Name</th>
                        <th class="manage-column">Created at</th>
                        <th class="manage-column">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $new_staff = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_new_staff ORDER BY staff_id DESC;");
                    $counter = 1;
                    foreach ($new_staff as $ns) {
                        $staff = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_staff WHERE id=" . $ns->staff_id . ";");
                        foreach ($staff as $s) {
                            $staff_name = $s->full_name;
                        }
                        ?>
                        <tr>
                            <td><?php echo $counter; ?></td>
                            <td><?php echo $staff_name; ?></td>
                            <td><?php echo $ns->created_at; ?></td>
                            <td>
                                <button class="button action"
                                    onclick="getConfirmation(<?php echo $ns->staff_id; ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php
                        $counter++;
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div id="modal" class="modal">
            <div class="modal-content">
                <h2>Are you sure?</h2>
                <p>Do you really want to delete this record? This process can't be undone.</p>
                <br />
                <div class="flex-container">
                    <button class="button button-primary" id="cancel">Cancel</button>
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="submit" class="button button-primary" name="delete" value="Delete">
                    </form>
                </div>
            </div>
        </div>
        <script>
            var modal = document.getElementById("modal");
            function getConfirmation(staff_id) {
                modal.style.display = "block";
                document.cookie = "jung_staff_id=" + staff_id;
            }
            var cancel = document.getElementById("cancel");
            cancel.onclick = function () {
                modal.style.display = "none";
            }
        </script>
    </div>
    <?php
}
function blacklist()
{
    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Blacklist</h1>
        <?php
        if (isset($_POST["submit"])) {
            if (
                $wpdb->insert($wpdb->prefix . "jung_blacklist", [
                    "phone" => $_POST["phone"]
                ], ["%s"]) != ""
            ) {
                echo '<div class="notice notice-success"> 
                        <p><strong>Submitted successfully.</strong></p>
                    </div>';
            } else {
                echo '<div class="notice notice-error"> 
                        <p><strong>Error! submit failed.</strong></p>
                    </div>';
            }
        }
        if (isset($_POST["delete"])) {
            if ($wpdb->delete($wpdb->prefix . "jung_blacklist", ["id" => (int) $_COOKIE["jung_blacklist"]], ["%d"]) == 1) {
                echo '<div class="notice notice-success"> 
                    <p><strong>Deleted successfully.</strong></p>
                </div>';
            } else {
                echo '<div class="notice notice-error"> 
                        <p><strong>Error! please try again later.</strong></p>
                    </div>';
            }
        }
        if (isset($_POST["submit-excel"])) {
            $success = "";
            $error = "";
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($_FILES['excel']['tmp_name']);
            $sheet = $spreadsheet->getSheet($spreadsheet->getFirstSheetIndex());
            $data = $sheet->toArray();
            foreach ($data as $item) {
                if ($item[0] != "") {
                    if (
                        $wpdb->insert($wpdb->prefix . "jung_blacklist", [
                            "phone" => $item[0]
                        ], ["%s"]) != ""
                    ) {
                        $success .= "Submitted successfully. (" . $item[0] . ")" . "<br/>";
                    } else {
                        $error .= "Error! submit failed. (" . $item[0] . ")" . "<br/>";
                    }
                }
            }
            if ($success != "") {
                echo '<div class="notice notice-success"> 
                    <p><strong>' . $success . '</strong></p>
                </div>';
            }
            if ($error != "") {
                echo '<div class="notice notice-error"> 
                    <p><strong>' . $error . '</strong></p>
                </div>';
            }
        }
        ?>
        <div class="card">
            <div class="card-body">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <form method="post" action="" enctype="multipart/form-data">
                                <td class="label">
                                    <label>Phone</label>
                                </td>
                                <td class="input">
                                    <input type="text" required name="phone">
                                </td>
                                <td>
                                    <input type="submit" class="button button-primary" name="submit" value="Add">
                                </td>
                                <td></td>
                            </form>
                        </tr>
                        <tr>
                            <form method="post" action="" enctype="multipart/form-data">
                                <td class="label">
                                    <label>Excel</label>
                                </td>
                                <td class="input">
                                    <input type="file" required name="excel">
                                </td>
                                <td>
                                    <input type="submit" class="button button-primary" name="submit-excel" value="Import">
                                </td>
                                <td></td>
                            </form>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <br />
        <hr />
        <br />
        <div style="overflow-x:auto">
            <table class="wp-list-table widefat striped table-view-list">
                <thead>
                    <tr>
                        <th class="manage-column">ID</th>
                        <th class="manage-column">Phone</th>
                        <th class="manage-column">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_blacklist ORDER BY id DESC;");
                    $counter = 1;
                    foreach ($rows as $row) {
                        ?>
                        <tr>
                            <td><?php echo $row->id; ?></td>
                            <td><?php echo $row->phone; ?></td>
                            <td>
                                <button class="button action" onclick="getConfirmation(<?php echo $row->id; ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php
                        $counter++;
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div id="modal" class="modal">
            <div class="modal-content">
                <h2>Are you sure?</h2>
                <p>Do you really want to delete this record? This process can't be undone.</p>
                <br />
                <div class="flex-container">
                    <button class="button button-primary" id="cancel">Cancel</button>
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="submit" class="button button-primary" name="delete" value="Delete">
                    </form>
                </div>
            </div>
        </div>
        <script>
            var modal = document.getElementById("modal");
            function getConfirmation(id) {
                modal.style.display = "block";
                document.cookie = "jung_blacklist=" + id;
            }
            var cancel = document.getElementById("cancel");
            cancel.onclick = function () {
                modal.style.display = "none";
            }
        </script>
    </div>
    <?php
}
?>