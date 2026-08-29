<?php
$permissions = [
    [
        6205,
        [
            "https://daroon.me/wp-admin/admin.php?page=bookly-appointments",
            "https://daroon.me/wp-admin/profile.php",
        ],
        ["bookly-cloud-menu","index.php"],
        [
            ["bookly-menu", "bookly-dashboard"],
            ["bookly-menu", "bookly-calendar"],
            ["bookly-menu", "bookly-packages"],
            ["bookly-menu", "bookly-staff"],
            ["bookly-menu", "bookly-services"],
            ["bookly-menu", "bookly-customers"],
            ["bookly-menu", "bookly-discounts"],
            ["bookly-menu", "bookly-notifications"],
            ["bookly-menu", ""],
            ["bookly-menu", "bookly-cloud-sms"],
            ["bookly-menu", "bookly-payments"],
            ["bookly-menu", "bookly-appearance"],
            ["bookly-menu", "bookly-coupons"],
            ["bookly-menu", "bookly-settings"],
            ["bookly-menu", "bookly-diagnostics"],
            ["bookly-menu", "bookly-news"],
            ["bookly-menu", "bookly-shop"],
        ]
    ]
];
add_action("admin_head", "only_appointments");
function only_appointments(){
    $user = wp_get_current_user()->ID;
    $page = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    global $permissions;
    foreach($permissions as $permission){
        if($user == $permission[0]){
            if(in_array($page, $permission[1])){
                foreach($permission[2] as $menu){
                    remove_menu_page($menu);
                }
                foreach($permission[3] as $sub_menu){
                    remove_submenu_page($sub_menu[0], $sub_menu[1]);
                }
                if($permission[0] == 6205){
                    add_action("admin_footer", "remove_element");
                    function remove_element(){
                        echo "<script src='https://daroon.me/wp-content/plugins/jung/jquery-3.7.1.min.js'></script>";
                        echo "<script>$('*[data-target=" . '"' . "#bookly-export-dialog" . '"' . "]').remove();</script>";
                        echo "<script>$('*[data-target=" . '"' . "#bookly-print-dialog" . '"' . "]').remove();</script>";
                    }
                }
            } else {
                wp_redirect("https://daroon.me/wp-admin/admin.php?page=bookly-appointments");
                exit;
            }
        }
    }
}
?>