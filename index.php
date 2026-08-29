<?php
ob_start();
require __DIR__.'/vendor/autoload.php';
/*
Plugin Name: Jung
Plugin URI: https://simyatech.com
Description: New features for daroon.me
Version: 1.0.0
Author: Erfan Momeni
*/
require __DIR__.'/permissions.php';
function add_menu(){
    add_menu_page("Finances", "Finances","manage_options", "jung-invoices", "invoices", "", 2);
    add_submenu_page("jung-invoices", "Invoices", "Invoices", "manage_options", "jung-invoices", "invoices");
    add_submenu_page("jung-invoices", "Users", "Users", "manage_options", "jung-invoice-users", "users");
    add_submenu_page("jung-invoices", "Exchange Rate Logs", "Exchange Rate Logs", "manage_options", "jung-exchange-rate-logs", "exchange_rate_logs");
    add_submenu_page("jung-invoices", "Therapists Info", "Therapists Info", "manage_options", "jung-staff_info", "staff_info");
    add_submenu_page("", "Upload Invoice", "Upload Invoice", "manage_options", "jung-upload_invoice", "upload_invoice");
    add_submenu_page("", "Edit User", "Edit User", "manage_options", "jung-edit-user", "update_user");
    add_menu_page("Reports", "Reports","manage_options", "jung-customers-report", "reports_1", "", 2);
    add_submenu_page("jung-customers-report", "Customers Report", "Customers Report", "manage_options", "jung-customers-report", "reports_1");
    add_submenu_page("jung-customers-report", "Evaluation Schedule", "Evaluation Schedule", "manage_options", "jung-evaluation-schedule", "reports_0");
    add_submenu_page("jung-customers-report", "WhatsApp Logs", "WhatsApp Logs", "manage_options", "jung-wa-logs", "wa_logs");
    add_submenu_page("jung-customers-report", "Email Logs", "Email Logs", "manage_options", "jung-mail-logs", "mail_logs");
    add_submenu_page("jung-customers-report", "Therapists URL", "Therapists URL", "manage_options", "jung-staff-urls", "staff_urls");
    add_submenu_page("jung-customers-report", "New Therapists", "New Therapists", "manage_options", "jung-new-staff", "new_staff");
    add_submenu_page("jung-customers-report", "Blacklist", "Blacklist", "manage_options", "jung-blacklist", "blacklist");
    add_submenu_page("jung-customers-report", "Coupons", "Coupons", "manage_options", "jung-coupons", "coupons");
}
add_action("admin_menu", "add_menu");
require __DIR__.'/app/finances.php';
require __DIR__.'/app/reports.php';
?>