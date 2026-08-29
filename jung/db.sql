CREATE TABLE IF NOT EXISTS wp_jung_invoice_users (
    customer_id int(11) NOT NULL,
    currency int(1) NOT NULL,
    from_date DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (customer_id)
);
CREATE TABLE IF NOT EXISTS wp_jung_invoices (
    id int(11) NOT NULL AUTO_INCREMENT,
    customer_id int(11) NOT NULL,
    payment_id int(11) NOT NULL,
    name varchar(255) NOT NULL,
    sent_at DATETIME,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id)
);
CREATE TABLE IF NOT EXISTS wp_jung_rate_logs (
    id int(11) NOT NULL AUTO_INCREMENT,
    payment_id int(11) NOT NULL,
    response json NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id)
);
CREATE TABLE IF NOT EXISTS wp_jung_files (
    id int(11) NOT NULL AUTO_INCREMENT,
    type int(11) NOT NULL,
    name varchar(255) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id)
);
CREATE TABLE IF NOT EXISTS wp_jung_staff_urls (
    staff_id int(11) NOT NULL,
    slug varchar(255) NOT NULL,
    PRIMARY KEY (staff_id)
);
CREATE TABLE IF NOT EXISTS wp_jung_notif_logs (
    id int(11) NOT NULL AUTO_INCREMENT,
    appointment_id int(11) NOT NULL,
    customer_id int(11) NOT NULL,
    channel int(11) NOT NULL,
    type int(11) NOT NULL,
    response longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id)
);
CREATE TABLE IF NOT EXISTS wp_jung_new_staff (
    staff_id int(11) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (staff_id)
);
CREATE TABLE IF NOT EXISTS wp_jung_blacklist (
    id int(11) NOT NULL AUTO_INCREMENT,
    phone varchar(255) NOT NULL,
    PRIMARY KEY (id)
);
CREATE TABLE IF NOT EXISTS wp_jung_staff_info (
    staff_id int(11) NOT NULL,
    var1 varchar(255),
    var2 varchar(255),
    var3 varchar(255),
    var4 varchar(255),
    PRIMARY KEY (staff_id)
);