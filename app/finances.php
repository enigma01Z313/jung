<?php
use Spipu\Html2Pdf\Html2Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if( isset($_POST['resolve_invoice_error']) ){
    $time_zone = ( isset($_POST['timezone_code_f']) && !empty($_POST['timezone_code_f']) )? $_POST['timezone_code_f'] : '';
    $id = ( isset($_POST['customer_appointment_id']) && !empty($_POST['customer_appointment_id']) )? $_POST['customer_appointment_id'] : '';
    if ($time_zone == '') return;
    if ($id == '') return;

    global $wpdb;
    $table_name = $wpdb->prefix . "bookly_customer_appointments";

    $data           = ['time_zone'  => $time_zone];
    $where          = ['id'         =>  $id];
    $format         = ['%s'];
    $where_format   = ['%d'];

    $updated = $wpdb->update( $table_name, $data, $where, $format, $where_format );
}
function invoice_exec_error_detected ($row) {
    ?>
        <section>
            <h1><b>There was problem when exporting invoices!</b></h1>
            <div>
                <h3>Timezone of some "bookly_appointment" has not been set properly</h3>
                <table border="1px">
                    <thead>
                        <tr>
                            <th style="padding: 6px 12px; text-align: left">Customer ID</th>
                            <th style="padding: 6px 12px; text-align: left">Appointment ID</th>
                            <th style="padding: 6px 12px; text-align: left">Staff ID</th>
                            <th style="padding: 6px 12px; text-align: left">Internal Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 6px 12px; text-align: left"><?= $row->customer_id ?></td>
                            <td style="padding: 6px 12px; text-align: left"><?= $row->appointment_id ?></td>
                            <td style="padding: 6px 12px; text-align: left"><?= $row->staff_id ?></td>
                            <td style="padding: 6px 12px; text-align: left"><?= $row->internal_note ?></td>
                        </tr>
                    </tbody>
                </table>

                <h3 style="margin-top: 40px">Set appointment timezone based on customer's old appointments</h3>
                <?php
                    global $wpdb;
                    $table_name = $wpdb->prefix . "bookly_customer_appointments";
                    $costumer_id = $row->customer_id;

                    $query = "  SELECT time_zone FROM $table_name 
                                WHERE customer_id = $costumer_id
                                AND time_zone IS NOT NULL";
                    $appointments = $wpdb->get_results($query);
                    if(sizeof($appointments) == 0) echo "<div>No appointment found</div>";
                    else{
                        foreach($appointments as $appointment){
                            $time_zone = $appointment->time_zone;
                            echo "<div>$time_zone</div>";
                        }
                    }
                ?>
                <form style="margin-top: 16px" method="post" action="" enctype="multipart/form-data">
                    <input type="hidden" name="customer_appointment_id" value="<?= $row->id; ?>" />
                    Set timezone: <input type="text" name="timezone_code_f" value="">
                    <input class="button button-primary" type="submit" name="resolve_invoice_error" value="Resolve">
                </form>
            </div>
        </section>
    <?php
}

error_reporting(E_ALL - E_NOTICE - E_WARNING);
function invoices_exec()
{
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_invoice_users;");
    foreach ($rows as $row) {
        $customer_id = $row->customer_id;
        $currency = $row->currency;
        $from_date = $row->from_date;

        
        $tmpSql = " SELECT * FROM " . $wpdb->prefix . "bookly_appointments AS a 
                    INNER JOIN " . $wpdb->prefix . "bookly_customer_appointments AS ca 
                        ON a.id = ca.appointment_id 
                        AND ca.customer_id=" . $customer_id . " 
                        AND ca.status='approved' 
                        AND a.start_date>='" . $from_date . "' 
                    ORDER BY a.id DESC;";
        $rows = $wpdb->get_results($tmpSql);
            
        foreach ($rows as $row) {
            $end_date = $row->end_date;
            $payment_id = $row->payment_id;
            if (!$payment_id)
                continue;

            if(is_null($row->time_zone)){
                invoice_exec_error_detected($row);
                break;
            }

            $time_zone = $row->time_zone;
            $submitted_on = new DateTime($end_date, new DateTimeZone('Asia/Tehran'));
            $submitted_on->setTimezone(new DateTimeZone($time_zone));
            $submitted_on = $submitted_on->format("d/m/Y");
            $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_invoices WHERE payment_id=" . $payment_id . ";");
            if (empty($rows)) {
                if ($end_date < date("Y-m-d H:i:s")) {
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_invoices ORDER BY id DESC LIMIT 1;");
                    $no = 13319;
                    foreach ($rows as $row) {
                        $no += $row->id;
                    }
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customers WHERE id=" . $customer_id . ";");
                    foreach ($rows as $row) {
                        $full_name = ucwords($row->full_name);
                        $email = $row->email;
                        $phone = $row->phone;
                    }
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_payments WHERE id=" . $payment_id . " AND status='completed';");
                    foreach ($rows as $row) {
                        $payment_created_at = new DateTime($row->created_at, new DateTimeZone('Asia/Tehran'));
                        $payment_created_at->setTimezone(new DateTimeZone($time_zone));
                        $payment_created_at = $payment_created_at->format("Y-m-d");
                        $details = json_decode($row->details);
                        $paid = $row->paid;
                        if ($currency != 0) {
                            $access_key = "3537ee5cfb09ac6d1a3d2d874de56cd9";
                            $url = "http://api.exchangeratesapi.io/v1/" . $payment_created_at . "?access_key=" . $access_key . "&base=USD&symbols=EUR,CAD";
                            $curl = curl_init($url);
                            curl_setopt($curl, CURLOPT_URL, $url);
                            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($curl);
                            curl_close($curl);
                            $wpdb->insert($wpdb->prefix . "jung_rate_logs", [
                                "payment_id" => $payment_id,
                                "response" => $response,
                                "created_at" => date("Y-m-d H:i:s")
                            ], ["%d", "%s", "%s"]);
                            $response = json_decode($response);
                        }
                        $rate = 0;
                        $sign = "";
                        $currency_acronyms = "";
                        switch ($currency) {
                            case 0:
                                $currency_acronyms = "USD";
                                $sign = "$";
                                $rate = 1;
                                break;
                            case 1:
                                $currency_acronyms = "CAD";
                                $sign = "C$";
                                if ($response->success) {
                                    $rate = $response->rates->CAD;
                                }
                                break;
                            case 2:
                                $currency_acronyms = "EUR";
                                $sign = "€";
                                if ($response->success) {
                                    $rate = $response->rates->EUR;
                                }
                                break;
                        }
                        $pdf = '<page backtop="14mm" backbottom="14mm" backleft="10mm" backright="10mm">
                            <table style="color:#49496b;width:100%;font-size:10px;">
                                <tr>
                                    <td style="font-size:10px;font-weight:bold;">Daroon Wellness Inc.</td>
                                </tr>
                                <tr>
                                    <td>329 Howe St, Unit #740</td>
                                </tr>
                                <tr>
                                    <td>Vancouver, BC, Canada V6C 3N2</td>
                                </tr>
                                <tr>
                                    <td>(604) 245-7244</td>
                                </tr>
                            </table>
                            <br/>
                            <table style="color:#49496b;width:100%;">
                                <tr>
                                    <td style="width:50%;font-size:34px;font-weight:bold;">Invoice</td>
                                    <td style="width:50%;font-size:26px;font-weight:bold;">PAID</td>
                                </tr>
                                <tr>
                                    <td style="width:50%;font-size:12px;font-weight:bold;">Submitted on ' . $submitted_on . '</td>
                                    <td style="width:50%;"></td>
                                </tr>
                            </table>
                            <br/>
                            <table style="color:#49496b;width:100%;">
                                <tr style="font-size:12px;font-weight:bold;">
                                    <td style="width:50%;">Invoice for</td>
                                    <td style="width:50%;">Invoice #</td>
                                </tr>
                                <tr style="font-size:10px;">
                                    <td style="width:50%;">' . $full_name . '</td>
                                    <td style="width:50%;">DRN' . $no . '</td>
                                </tr>
                            </table>
                            <br/>
                            <table style="color:#49496b;width:100%;">
                                <tr style="font-size:10px;">
                                    <td class="width:50%;">' . $email . '</td>
                                    <td class="width:50%;"></td>
                                </tr>
                                <tr style="font-size:10px;">
                                    <td class="width:50%;">' . $phone . '</td>
                                    <td class="width:50%;"></td>
                                </tr>
                            </table>
                            <br/>
                            <br/>
                            <table style="color:#49496b;width:100%;">
                                <thead>
                                    <tr style="font-size:12px;">
                                        <th style="width:35%;height:35px;line-height:15px;">Description</th>
                                        <th style="width:35%;height:35px;line-height:15px;">Therapist</th>
                                        <th style="width:15%;height:35px;line-height:15px;">Session Date</th>
                                        <th style="width:15%;height:35px;line-height:15px;">Price ' . $currency_acronyms . '</th>
                                    </tr>
                                </thead>
                                <tbody>';
                        $docx = '<html>
                        <head>
                            <meta charset="UTF-8"/>
                        </head>
                        <body style="color:#49496b;">
                            <table style="width:100%;border:none;">
                                <tr>
                                    <td style="font-size:10px;font-weight:bold;">Daroon Wellness Inc.</td>
                                </tr>
                                <tr>
                                    <td style="font-size:10px;">329 Howe St, Unit #740</td>
                                </tr>
                                <tr>
                                    <td style="font-size:10px;">Vancouver, BC, Canada V6C 3N2</td>
                                </tr>
                                <tr>
                                    <td style="font-size:10px;">(604) 245-7244</td>
                                </tr>
                            </table>
                            <br/>
                            <table style="width:100%;border:none;">
                                <tr>
                                    <td style="font-size:34px;font-weight:bold;width:50%;">Invoice</td>
                                    <td style="font-size:26px;font-weight:bold;width:50%;">PAID</td>
                                </tr>
                                <tr>
                                    <td style="font-size:12px;font-weight:bold;width:50%;">Submitted on ' . $submitted_on . '</td>
                                    <td style="width: 50%;"></td>
                                </tr>
                            </table>
                            <br/>
                            <table style="width:100%;border:none;">
                                <tr style="font-size:12px;">
                                    <td style="font-weight:bold;width:50%;">Invoice for</td>
                                    <td style="font-weight:bold;width:50%;">Invoice #</td>
                                </tr>
                                <tr style="font-size:10px;">
                                    <td style="width:50%;">' . $full_name . '</td>
                                    <td style="width:50%;">DRN' . $no . '</td>
                                </tr>
                            </table>
                            <br/>
                            <table style="width:100%;border:none;">
                                <tr>
                                    <td style="font-size:10px;width:50%;">' . $email . '</td>
                                    <td style="width:50%;"></td>
                                </tr>
                                <tr>
                                    <td style="font-size:10px;width:50%;">' . $phone . '</td>
                                    <td style="width:50%;"></td>
                                </tr>
                            </table>
                            <br/>
                            <table style="width: 100%;border:none;">
                                <thead>
                                    <tr style="font-size:12px;font-weight:bold;">
                                        <th style="width:30%;">Description</th>
                                        <th style="width:35%;">Therapist</th>
                                        <th style="width:20%;">Session Date</th>
                                        <th style="width:15%;">Price ' . $currency_acronyms . '</th>
                                    </tr>
                                </thead>
                                <tbody>';
                        $c = 0;
                        $total = 0;
                        foreach ($details->items as $item) {
                            $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customer_appointments WHERE id=" . $item->ca_id . ";");
                            foreach ($rows as $row) {
                                $appointment_id = $row->appointment_id;
                                $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_appointments WHERE id=" . $appointment_id . ";");
                                foreach ($rows as $row) {
                                    $staff_id = $row->staff_id;
                                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_staff_info WHERE staff_id=" . $staff_id . ";");
                                    $info = "";
                                    foreach ($rows as $row) {
                                        if ($row->var1 != "") {
                                            $info .= $row->var1 . "<br/>";
                                        }
                                        if ($row->var2 != "") {
                                            $info .= $row->var2 . "<br/>";
                                        }
                                        if ($row->var3 != "") {
                                            $info .= $row->var3 . "<br/>";
                                        }
                                        if ($row->var4 != "") {
                                            $info .= $row->var4 . "<br/>";
                                        }
                                    }
                                }
                            }
                            $session_date = new DateTime($item->appointment_date, new DateTimeZone('Asia/Tehran'));
                            $session_date->setTimezone(new DateTimeZone($time_zone));
                            $session_date = $session_date->format("d/m/Y");
                            $pdf .= '<tr style="font-size:10px;';
                            $docx .= '<tr style="font-size:10px;';
                            if ($c % 2 == 0) {
                                $pdf .= 'background-color:#f3f3f3;';
                                $docx .= 'background-color:#f3f3f3;';
                            }
                            $c++;
                            $service_price = number_format($item->service_price, "6");
                            $service_price *= $rate;
                            $total += $service_price;
                            $service_price = floor(($service_price * 100)) / 100;
                            $pdf .= '">
                                <td style="width:35%;height:35px;line-height:15px;">Psychotherapy Session - 45 minutes</td>
                                <td style="width:35%;height:35px;line-height:15px;">' . $info . '</td>
                                <td style="width:15%;height:35px;line-height:15px;">' . $session_date . '</td>
                                <td style="width:15%;height:35px;line-height:15px;">' . $sign . $service_price . '</td>
                            </tr>';
                            $docx .= '">
                                <td style="width:35%;">Psychotherapy Session - 45 minutes</td>
                                <td style="width:35%;">' . $info . '</td>
                                <td style="width:15%;">' . $session_date . '</td>
                                <td style="width:15%;">' . $sign . $service_price . '</td>
                            </tr>';
                        }
                        $total = floor(($total * 100)) / 100;
                        $paid = number_format($paid, "6");
                        $paid *= $rate;
                        $paid = floor(($paid * 100)) / 100;
                        $discount = $total - $paid;
                        $pdf .= '</tbody>
                        </table>
                        <br/>
                        <br/>
                        <table style="color:#49496b;width:100%;text-align:right;">
                            <tr style="font-size:10px;">
                                <td style="width:35%;"></td>
                                <td style="width:35%;"></td>
                                <td style="width:15%;">Subtotal:</td>
                                <td style="width:15%;font-weight:bold;">' . $sign . $total . '</td>
                            </tr>
                            <tr style="font-size:10px;">
                                <td style="width:35%;"></td>
                                <td style="width:35%;"></td>
                                <td style="width:15%;">Adjustments:</td>
                                <td style="width:15%;font-weight:bold;">';
                        if ($discount != 0) {
                            $pdf .= "- " . $sign . $discount;
                        }
                        $pdf .= '</td>
                            </tr>
                            <tr style="font-size:10px;">
                                <td style="width:35%;"></td>
                                <td style="width:35%;"></td>
                                <td style="width:15%;">Total:</td>
                                <td style="width:15%;font-weight:bold;">' . $sign . $paid . '</td>
                            </tr>
                        </table>
                        <page_footer>
                            <table style="color:#49496b;width:100%;margin:25px;font-size:10px;">
                                <tr>
                                    <td>Daroon wellness Inc.</td>
                                </tr>
                                <tr>
                                    <td>Business #: 797154804</td>
                                </tr>
                            </table>
                        </page_footer></page>';
                        $docx .= '</tbody>
                                </table>
                                <br/>
                                <table style="width:100%;font-size:10px;">
                                    <tr>
                                        <td style="width:35%;"></td>
                                        <td style="width:35%;"></td>
                                        <td style="width:15%;text-align:right;">Subtotal:</td>
                                        <td style="width:15%;font-weight:bold;text-align:right;">' . $sign . $total . '</td>
                                    </tr>
                                    <tr>
                                        <td style="width:35%;"></td>
                                        <td style="width:35%;"></td>
                                        <td style="width:15%;text-align:right;">Adjustments:</td>
                                        <td style="width:15%;font-weight:bold;text-align:right;">';
                        if ($discount != 0) {
                            $docx .= "- " . $sign . $discount;
                        }
                        $docx .= '</td>
                                    </tr>
                                    <tr>
                                        <td style="width:35%;"></td>
                                        <td style="width:35%;"></td>
                                        <td style="width:15%;text-align:right;">Total:</td>
                                        <td style="width:15%;font-size:14px;font-weight:bold;text-align:right;">' . $sign . $paid . '</td>
                                    </tr>
                                </table>
                            </body>
                        </html>';
                        $path = WP_CONTENT_DIR . "/uploads/jung_files/";
                        $file_name = str_replace(" ", "", $full_name) . '-' . $customer_id . $payment_id . "-" . date("His");
                        $html2pdf = new Html2Pdf('P', 'A4', 'en', true, 'UTF-8', array(0, 0, 0, 0));
                        $html2pdf->writeHTML($pdf);
                        // ob_end_clean();
                        $html2pdf->output($path . $file_name . ".pdf", "F");
                        $phpWord = new \PhpOffice\PhpWord\PhpWord();
                        $phpWord->setDefaultFontName("Aria");
                        $section = $phpWord->addSection();
                        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $docx, false, false);
                        $footer = $section->addFooter();
                        $textrun = $footer->addTextRun();
                        $style['name'] = "Aria";
                        $style['size'] = 7.5;
                        $style['color'] = "49496b";
                        $textrun->addText("Daroon wellness Inc.", $style);
                        $textrun->addTextBreak();
                        $textrun->addText("Business #: 797154804", $style);
                        $phpWord->save($path . $file_name . ".docx", "Word2007");
                        $wpdb->insert($wpdb->prefix . "jung_invoices", [
                            "customer_id" => $customer_id,
                            "payment_id" => $payment_id,
                            "name" => $file_name,
                            "created_at" => date("Y-m-d H:i:s")
                        ], ["%d", "%d", "%s", "%s"]);
                    }
                }
            }
        }
    }
}
function invoices()
{
    if ($_GET["uploaded"] == "true") {
        echo '<div class="notice notice-success"> 
            <p><strong>Uploaded successfully.</strong></p>
        </div>';
    }
    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    function jung_set_html_content_type()
    {
        return 'text/html';
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Invoices</h1>
        <br />
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
        if (isset($_POST["send"])) {
            $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_invoices WHERE id=" . $_COOKIE["jung_invoice_id"] . ";");
            foreach ($rows as $row) {
                $customer_id = $row->customer_id;
                $name = $row->name;
            }
            $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customers WHERE id=" . $customer_id . ";");
            foreach ($rows as $row) {
                $email = $row->email;
            }
            $from = "support@daroon.me";
            $subject = "Invoice";
            $message = "
            <p>Dear Daroon Client,</p><br/>
            <p>Attached is the invoice(s) for your therapy sessions with Daroon Wellness Inc.</p><br/>
            <p>Thank you for choosing Daroon to be by your side on your journey of healing and personal growth.</p><br/>
            <p>Please let us know if you need assistance with anything else.</p><br/>
            <p>Best,</p><br/>
            <p>Daroon Team</p><br/>
            <p>www.daroon.me</p>
            ";
            $attachments = array(WP_CONTENT_DIR . "/uploads/jung_files/" . $name . ".pdf");
            $headers = 'From: daroon <' . $from . '>' . '\r\n';
            $headers .= 'Cc: daroon.payments@gmail.com' . '\r\n';
            add_filter($wpdb->prefix . "mail_content_type", "jung_set_html_content_type");
            $response = wp_mail($email, $subject, $message, $headers, $attachments);
            remove_filter($wpdb->prefix . "mail_content_type", "jung_set_html_content_type");
            if ($response) {
                $wpdb->update($wpdb->prefix . "jung_invoices", [
                    'sent_at' => date("Y-m-d H:i:s")
                ], ['id' => (int) $_COOKIE['jung_invoice_id']], ['%s'], ['%d']);
                echo '<div class="notice notice-success"> 
                    <p><strong>Email sent successfully.</strong></p>
                </div>';
            } else {
                echo '<div class="notice notice-error"> 
                        <p><strong>Error! please try again later.</strong></p>
                    </div>';
            }
        }
        if (isset($_POST["delete"])) {
            $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_invoices WHERE id=" . $_COOKIE["jung_invoice_id"] . ";");
            foreach ($rows as $row) {
                $name = $row->name;
            }
            unlink(WP_CONTENT_DIR . "/uploads/jung_files/" . $name . ".pdf");
            unlink(WP_CONTENT_DIR . "/uploads/jung_files/" . $name . ".docx");
            if ($wpdb->delete($wpdb->prefix . "jung_invoices", ["id" => (int) $_COOKIE["jung_invoice_id"]], ["%d"]) == 1) {
                echo '<div class="notice notice-success"> 
                    <p><strong>Deleted successfully.</strong></p>
                </div>';
            } else {
                echo '<div class="notice notice-error"> 
                    <p><strong>Error! please try again later.</strong></p>
                </div>';
            }
        }
        if (isset($_POST["export"])) {
            invoices_exec();
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
                        <th class="manage-column">Payment</th>
                        <th class="manage-column">Name</th>
                        <th class="manage-column">Email</th>
                        <th class="manage-column">Sent at</th>
                        <th class="manage-column">Created at</th>
                        <th class="manage-column">Action</th>
                    </tr>
                </thead>
                <?php
                $invoices = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_invoices WHERE created_at BETWEEN '" . $from . "' AND '" . $to . "' ORDER BY id DESC;");
                foreach ($invoices as $i) {
                    $customer = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customers WHERE id = " . $i->customer_id . ";");
                    foreach ($customer as $c) {
                        ?>
                        <tr>
                            <td><?php echo $i->id; ?></td>
                            <td><?php echo $i->payment_id; ?></td>
                            <td><?php echo $c->full_name; ?></td>
                            <td><?php echo $c->email; ?></td>
                            <td>
                                <?php
                                if ($i->sent_at == "0000-00-00 00:00:00") {
                                    echo "Pending";
                                } else {
                                    echo $i->sent_at;
                                }
                                ?>
                            </td>
                            <td><?php echo $i->created_at; ?></td>
                            <td>
                                <div class="flex-container">
                                    <button class="button action"
                                        onclick="window.open('<?php echo get_site_url() . "/wp-content/uploads/jung_files/" . $i->name . ".pdf"; ?>', '_blank')">PDF</button>
                                    <button class="button action"
                                        onclick="window.open('<?php echo get_site_url() . "/wp-content/uploads/jung_files/" . $i->name . ".docx"; ?>', '_blank')">DOCX</button>
                                    <button class="button action"
                                        onclick="location.href='/wp-admin/admin.php?page=jung-upload_invoice&invoice_id=<?php echo $i->id; ?>'">Upload</button>
                                    <button class="button action"
                                        onclick="<?php echo 'sendConfirmation(' . $i->id . ", '" . $i->sent_at . "')"; ?>">Send</button>
                                    <button class="button action"
                                        onclick="<?php echo 'deleteConfirmation(' . $i->id . ')'; ?>">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </table>
        </div>
        <div id="send-modal" class="modal">
            <div class="modal-content">
                <h2>Are you sure?</h2>
                <strong id="warning"></strong>
                <p>Do you really want to send this invoice? This process can't be undone.</p>
                <br />
                <div class="flex-container">
                    <button class="button action" id="send-cancel">Cancel</button>
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="submit" class="button action" name="send" value="Send">
                    </form>
                </div>
            </div>
        </div>
        <div id="delete-modal" class="modal">
            <div class="modal-content">
                <h2>Are you sure?</h2>
                <p>Do you really want to delete this record? This process can't be undone.</p>
                <br />
                <div class="flex-container">
                    <button class="button button-primary" id="delete-cancel">Cancel</button>
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="submit" class="button button-primary" name="delete" value="Delete">
                    </form>
                </div>
            </div>
        </div>
        <script>
            var send_modal = document.getElementById("send-modal");
            function sendConfirmation(id, sent_at) {
                send_modal.style.display = "block";
                document.cookie = "jung_invoice_id=" + id;
                document.getElementById("warning").innerHTML = "";
                if (sent_at != "0000-00-00 00:00:00") {
                    document.getElementById("warning").innerHTML = "Warning! this invoice has already been sent at " + sent_at + ".";
                }
            }
            var send_cancel = document.getElementById("send-cancel");
            send_cancel.onclick = function () {
                send_modal.style.display = "none";
            }
            var delete_modal = document.getElementById("delete-modal");
            function deleteConfirmation(id) {
                delete_modal.style.display = "block";
                document.cookie = "jung_invoice_id=" + id;
            }
            var delete_cancel = document.getElementById("delete-cancel");
            delete_cancel.onclick = function () {
                delete_modal.style.display = "none";
            }
        </script>
    </div>
    <?php
}
function Users()
{
    if ($_GET["edited"] == "true") {
        echo '<div class="notice notice-success"> 
            <p><strong>Edited successfully.</strong></p>
        </div>';
    }
    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Users</h1>
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
        if (isset($_POST["submit"])) {
            $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customers WHERE phone='" . $_POST["phone"] . "';");
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $customer_id = $row->id;
                }
                $d = $_POST["from_date"] . " 00:00:00";
                if (
                    $wpdb->insert($wpdb->prefix . "jung_invoice_users", [
                        "customer_id" => $customer_id,
                        "from_date" => $d,
                        "currency" => $_POST["currency"],
                        "created_at" => date("Y-m-d H:i:s")
                    ], ["%d", "%s", "%d", "%s"]) != ""
                ) {
                    echo '<div class="notice notice-success"> 
                            <p><strong>Submitted successfully.</strong></p>
                        </div>';
                } else {
                    echo '<div class="notice notice-error"> 
                            <p><strong>Error! submit failed.</strong></p>
                        </div>';
                }
            } else {
                echo '<div class="notice notice-error"> 
                        <p><strong>Error! user not found.</strong></p>
                    </div>';
            }
        }
        if (isset($_POST["delete"])) {
            if ($wpdb->delete($wpdb->prefix . "jung_invoice_users", ["customer_id" => (int) $_COOKIE["jung_cust_id"]], ["%d"]) == 1) {
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
                if ($item[0] != "" || $item[1] != "" || $item[2] != "") {
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customers WHERE phone='" . $item[0] . "';");
                    if (!empty($rows)) {
                        foreach ($rows as $row) {
                            $customer_id = $row->id;
                        }
                        $d = $item[1] . " 00:00:00";
                        if (
                            $wpdb->insert($wpdb->prefix . "jung_invoice_users", [
                                "customer_id" => $customer_id,
                                "from_date" => $d,
                                "currency" => $item[2],
                                "created_at" => date("Y-m-d H:i:s")
                            ], ["%d", "%s", "%d", "%s"]) != ""
                        ) {
                            $success .= "Submitted successfully. (" . $item[0] . ")" . "<br/>";
                        } else {
                            $error .= "Error! submit failed. (" . $item[0] . ")" . "<br/>";
                        }
                    } else {
                        $error .= "Error! not found. (" . $item[0] . ")" . "<br/>";
                    }
                } else {
                    $error .= "Error! empty cell." . "<br/>";
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
                                <td class="label">
                                    <label>From</label>
                                </td>
                                <td class="input">
                                    <input type="date" required name="from_date" style="width: 100%">
                                </td>
                                <td class="label">
                                    <label>Currency</label>
                                </td>
                                <td class="input">
                                    <input type="radio" id="u" name="currency" value="0" checked><label for="u">USD</label>
                                    <input type="radio" id="c" name="currency" value="1"><label for="c">CAD</label>
                                    <input type="radio" id="e" name="currency" value="2"><label for="e">EUR</label>
                                </td>
                                <td style="width: 10%">
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
                                <td style="width: 10%">
                                    <input type="submit" class="button button-primary" name="submit-excel" value="Import">
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </form>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <br />
        <hr />
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
                        <th class="manage-column">Row</th>
                        <th class="manage-column">Name</th>
                        <th class="manage-column">Phone</th>
                        <th class="manage-column">Email</th>
                        <th class="manage-column">Currency</th>
                        <th class="manage-column">From Date</th>
                        <th class="manage-column">Created at</th>
                        <th class="manage-column">Updated at</th>
                        <th class="manage-column">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_invoice_users WHERE created_at BETWEEN '" . $from . "' AND '" . $to . "' ORDER BY customer_id DESC;");
                    $counter = 1;
                    foreach ($users as $u) {
                        $customer = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customers WHERE id=" . $u->customer_id . ";");
                        foreach ($customer as $c) {
                            ?>
                            <tr>
                                <td><?php echo $counter; ?></td>
                                <td><?php echo $c->full_name; ?></td>
                                <td><?php echo $c->phone; ?></td>
                                <td><?php echo $c->email; ?></td>
                                <td>
                                    <?php
                                    switch ($u->currency) {
                                        case 0:
                                            echo "USD";
                                            break;
                                        case 1:
                                            echo "CAD";
                                            break;
                                        case 2:
                                            echo "EUR";
                                            break;
                                    }
                                    ?>
                                </td>
                                <td><?php echo $u->from_date; ?></td>
                                <td><?php echo $u->created_at; ?></td>
                                <td><?php echo $u->updated_at; ?></td>
                                <td>
                                    <div class="flex-container">
                                        <button class="button action"
                                            onclick="location.href='/wp-admin/admin.php?page=jung-edit-user&user_id=<?php echo $u->customer_id; ?>'">Edit</button>
                                        <button class="button action"
                                            onclick="getConfirmation(<?php echo $u->customer_id; ?>)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
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
            function getConfirmation(customer_id) {
                modal.style.display = "block";
                document.cookie = "jung_cust_id=" + customer_id;
            }
            var cancel = document.getElementById("cancel");
            cancel.onclick = function () {
                modal.style.display = "none";
            }
        </script>
    </div>
    <?php
}
function exchange_rate_logs()
{
    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
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
    <div class="wrap">
        <h1 class="wp-heading-inline">Exchange Rate Logs</h1>
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
                        <th class="manage-column">Payment</th>
                        <th class="manage-column">Response</th>
                        <th class="manage-column">Created at</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_rate_logs WHERE created_at BETWEEN '" . $from . "' AND '" . $to . "' ORDER BY id DESC;");
                    foreach ($rows as $row) {
                        ?>
                        <tr>
                            <td><?php echo $row->id; ?></td>
                            <td><?php echo $row->payment_id; ?></td>
                            <td><?php echo $row->response; ?></td>
                            <td><?php echo $row->created_at; ?></td>
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
function staff_info()
{
    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Therapists Info</h1>
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
        if (isset($_POST["submit"])) {
            if (
                $wpdb->insert($wpdb->prefix . "jung_staff_info", [
                    "staff_id" => $_POST["staff"],
                    "var1" => $_POST["var1"],
                    "var2" => $_POST["var2"],
                    "var3" => $_POST["var3"],
                    "var4" => $_POST["var4"],
                ], ["%d", "%s", "%s", "%s", "%s"]) != ""
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
            if ($wpdb->delete($wpdb->prefix . "jung_staff_info", ["staff_id" => (int) $_COOKIE["jung_staff_id"]], ["%d"]) == 1) {
                echo '<div class="notice notice-success"> 
                    <p><strong>Deleted successfully.</strong></p>
                </div>';
            } else {
                echo '<div class="notice notice-error"> 
                        <p><strong>Error! please try again later.</strong></p>
                    </div>';
            }
        }
        if (isset($_POST["export"])) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue("A1", "Name");
            $sheet->setCellValue("B1", "#1");
            $sheet->setCellValue("C1", "#2");
            $sheet->setCellValue("D1", "#3");
            $sheet->setCellValue("E1", "#4");
            $staff_info = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_staff_info;");
            $counter = 2;
            foreach ($staff_info as $si) {
                $staff = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_staff WHERE id=" . $si->staff_id . ";");
                foreach ($staff as $s) {
                    $sheet->setCellValue("A" . $counter, $s->full_name);
                    $sheet->setCellValue("B" . $counter, $si->var1);
                    $sheet->setCellValue("C" . $counter, $si->var2);
                    $sheet->setCellValue("D" . $counter, $si->var3);
                    $sheet->setCellValue("E" . $counter, $si->var4);
                }
                $counter++;
            }
            $path = "/home/daroon/domains/staging.daroon.me/public_html/wp-content/uploads/jung_files/";
            $file_name = "therapists-info-" . date("Y-m-d-H-i-s") . ".xlsx";
            $writer = new Xlsx($spreadsheet);
            $writer->save($path . $file_name);
            $wpdb->insert($wpdb->prefix . "jung_files", [
                "name" => $file_name,
                "type" => 1,
                "created_at" => date("Y-m-d H:i:s")
            ], ["%s", "%d", "%s"]);
            echo '<div class="notice notice-success"> 
                <p><strong>Successfully completed.</strong></p>
            </div>';
        }
        if (isset($_POST["delete-xlsx"])) {
            $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_files WHERE id=" . $_COOKIE["jung_file_id"] . ";");
            foreach ($rows as $row) {
                $name = $row->name;
            }
            unlink(WP_CONTENT_DIR . "/uploads/jung_files/" . $name);
            if ($wpdb->delete($wpdb->prefix . "jung_files", array("id" => $_COOKIE["jung_file_id"])) == 1) {
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
                                <td></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td class="label">
                                    <label>#1</label>
                                </td>
                                <td class="input">
                                    <input type="text" name="var1">
                                </td>
                                <td class="label">
                                    <label>#2</label>
                                </td>
                                <td class="input">
                                    <input type="text" name="var2">
                                </td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td class="label">
                                    <label>#3</label>
                                </td>
                                <td class="input">
                                    <input type="text" name="var3">
                                </td>
                                <td class="label">
                                    <label>#4</label>
                                </td>
                                <td class="input">
                                    <input type="text" name="var4">
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
                        <th class="manage-column">#1</th>
                        <th class="manage-column">#2</th>
                        <th class="manage-column">#3</th>
                        <th class="manage-column">#4</th>
                        <th class="manage-column">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_staff_info;");
                    $counter = 1;
                    foreach ($rows as $row) {
                        $staff_id = $row->staff_id;
                        $var1 = $row->var1;
                        $var2 = $row->var2;
                        $var3 = $row->var3;
                        $var4 = $row->var4;
                        $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_staff WHERE id=" . $staff_id . ";");
                        foreach ($rows as $row) {
                            $staff_name = $row->full_name;
                        }
                        ?>
                        <tr>
                            <td><?php echo $counter; ?></td>
                            <td><?php echo $staff_name; ?></td>
                            <td><?php echo $var1; ?></td>
                            <td><?php echo $var2; ?></td>
                            <td><?php echo $var3; ?></td>
                            <td><?php echo $var4; ?></td>
                            <td>
                                <button class="button action"
                                    onclick="getConfirmation(<?php echo $staff_id; ?>)">Delete</button>
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
        <br />
        <hr />
        <table class="form-table w50">
            <tbody>
                <form method="post" action="" enctype="multipart/form-data">
                    <tr>
                        <td class="jung-btn">
                            <input type="submit" class="button button-primary" name="export" value="Export">
                        </td>
                    </tr>
                </form>
            </tbody>
        </table>
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
                        <th class="manage-column">Name</th>
                        <th class="manage-column">Created at</th>
                        <th class="manage-column">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_files WHERE created_at BETWEEN '" . $from . "' AND '" . $to . "' AND type=1 ORDER BY id DESC;");
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
                                        onclick="getConfirmation2(<?php echo $row->id; ?>)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div id="modal-xlsx" class="modal">
            <div class="modal-content">
                <h2>Are you sure?</h2>
                <p>Do you really want to delete this record? This process can't be undone.</p>
                <br />
                <div class="flex-container">
                    <button class="button button-primary" id="cancel-xlsx">Cancel</button>
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="submit" class="button button-primary" name="delete-xlsx" value="Delete">
                    </form>
                </div>
            </div>
        </div>
        <script>
            var modal_xlsx = document.getElementById("modal-xlsx");
            function getConfirmation2(file_id) {
                modal_xlsx.style.display = "block";
                document.cookie = "jung_file_id=" + file_id;
            }
            var cancel_xlsx = document.getElementById("cancel-xlsx");
            cancel_xlsx.onclick = function () {
                modal_xlsx.style.display = "none";
            }
        </script>
    </div>
    <?php
}
function upload_invoice()
{
    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    $data = [];
    $invoices = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_invoices WHERE id=" . $_GET["invoice_id"] . ";");
    foreach ($invoices as $i) {
        $data["id"] = $i->id;
        $data["payment_id"] = $i->payment_id;
        if ($i->sent_at == "0000-00-00 00:00:00") {
            $data["sent_at"] = "Pending";
        } else {
            $data["sent_at"] = $i->sent_at;
        }
        $data["created_at"] = $i->created_at;
        $data["name"] = $i->name;
        $customer = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customers WHERE id = " . $i->customer_id . ";");
        foreach ($customer as $c) {
            $data["full_name"] = $c->full_name;
            $data["email"] = $c->email;
        }
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Upload Invoice</h1>
        <?php
        if (isset($_POST["submit"])) {
            $name = explode("-", $data["name"]);
            $new_name = $name[0] . "-" . $name[1] . "-" . date("His");
            if (move_uploaded_file($_FILES["pdf"]["tmp_name"], WP_CONTENT_DIR . "/uploads/jung_files/" . $new_name . ".pdf")) {
                unlink(WP_CONTENT_DIR . "/uploads/jung_files/" . $data["name"] . ".pdf");
                rename(WP_CONTENT_DIR . "/uploads/jung_files/" . $data["name"] . ".docx", WP_CONTENT_DIR . "/uploads/jung_files/" . $new_name . ".docx");
                $wpdb->update($wpdb->prefix . "jung_invoices", ['name' => $new_name], ['id' => $data['id']], ['%s'], ['%d']);
                header("Location: /wp-admin/admin.php?page=jung-invoices&uploaded=true");
            } else {
                echo '<div class="notice notice-error"> 
                            <p><strong>Error! upload failed.</strong></p>
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
                                    <label>PDF</label>
                                </td>
                                <td class="input">
                                    <input type="file" required accept="application/pdf" name="pdf">
                                </td>
                                <td style="width: 10%">
                                    <input type="submit" class="button button-primary" name="submit" value="Upload">
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
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
                        <th class="manage-column">ID</th>
                        <th class="manage-column">Payment</th>
                        <th class="manage-column">Name</th>
                        <th class="manage-column">Email</th>
                        <th class="manage-column">Sent at</th>
                        <th class="manage-column">Created at</th>
                        <th class="manage-column">Action</th>
                    </tr>
                </thead>

                <tr>
                    <td><?php echo $data["id"]; ?></td>
                    <td><?php echo $data["payment_id"]; ?></td>
                    <td><?php echo $data["full_name"]; ?></td>
                    <td><?php echo $data["email"]; ?></td>
                    <td><?php echo $data["sent_at"] ?></td>
                    <td><?php echo $data["created_at"]; ?></td>
                    <td>
                        <div class="flex-container">
                            <button class="button action"
                                onclick="document.location.href='<?php echo get_site_url() . '/wp-content/uploads/jung_files/' . $data['name'] . '.pdf'; ?>'">PDF</button>
                            <button class="button action"
                                onclick="document.location.href='<?php echo get_site_url() . '/wp-content/uploads/jung_files/' . $data['name'] . '.docx'; ?>'">DOCX</button>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <?php
}
function update_user()
{
    wp_enqueue_style('jung-style', '/wp-content/plugins/jung/style.css', false, '1.1', 'all');
    date_default_timezone_set("Asia/Tehran");
    global $wpdb;
    $users = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "jung_invoice_users WHERE customer_id=" . $_GET["user_id"] . ";");
    foreach ($users as $u) {
        $from = $u->from_date;
        $from = new DateTime($from);
        $from = $from->format('Y-m-d');
        $currency = $u->currency;
        $customers = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bookly_customers WHERE id=" . $_GET["user_id"] . ";");
        foreach ($customers as $c) {
            $phone = $c->phone;
        }
    }
    if (isset($_POST["submit"])) {
        $d = $_POST["from_date"] . " 00:00:00";
        if (
            $wpdb->update('".$wpdb->prefix."jung_invoice_users', [
                'currency' => $_POST["currency"],
                'from_date' => $d,
                'updated_at' => date("Y-m-d H:i:s")
            ], ['customer_id' => $_GET["user_id"]], ['%s'], ['%s'], ['%s'], ['%d'])
        ) {
            header("Location: /wp-admin/admin.php?page=jung-invoice-users&edited=true");
        } else {
            echo '<div class="notice notice-error"> 
                <p><strong>Error! edit failed.</strong></p>
            </div>';
        }
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Users</h1>
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
                                    <input type="text" required name="phone" value="<?php echo $phone; ?>" disabled>
                                </td>
                                <td class="label">
                                    <label>From</label>
                                </td>
                                <td class="input">
                                    <input type="date" required name="from_date" style="width: 100%"
                                        value="<?php echo $from; ?>">
                                </td>
                                <td class="label">
                                    <label>Currency</label>
                                </td>
                                <td class="input">
                                    <input type="radio" id="u" name="currency" value="0" <?php if ($currency == 0) {
                                        echo "checked";
                                    } ?>><label for="u">USD</label>
                                    <input type="radio" id="c" name="currency" value="1" <?php if ($currency == 1) {
                                        echo "checked";
                                    } ?>><label for="c">CAD</label>
                                    <input type="radio" id="e" name="currency" value="2" <?php if ($currency == 2) {
                                        echo "checked";
                                    } ?>><label for="e">EUR</label>
                                </td>
                                <td style="width: 10%">
                                    <input type="submit" class="button button-primary" name="submit" value="Edit">
                                </td>
                                <td></td>
                            </form>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
?>