<?php
// tech_system.php - computer parts inventory, buying, and PC build helpers

const PART_CATEGORIES = [
    'CPU',
    'CPU Cooler',
    'Motherboard',
    'Memory',
    'Storage',
    'Graphics Card',
    'Power Supply',
    'Case',
    'Monitor',
    'Keyboard',
    'Mouse',
    'Networking',
    'Accessory',
];

function ensure_tech_schema(mysqli $conn): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $conn->query("CREATE TABLE IF NOT EXISTS tech_parts (
        id INT(11) NOT NULL AUTO_INCREMENT,
        sku VARCHAR(60) NOT NULL,
        name VARCHAR(180) NOT NULL,
        category VARCHAR(60) NOT NULL,
        brand VARCHAR(100) DEFAULT NULL,
        model VARCHAR(120) DEFAULT NULL,
        socket VARCHAR(60) DEFAULT NULL,
        form_factor VARCHAR(60) DEFAULT NULL,
        wattage INT(11) DEFAULT NULL,
        stock INT(11) NOT NULL DEFAULT 0,
        reorder_level INT(11) NOT NULL DEFAULT 3,
        unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        sale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        supplier VARCHAR(150) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_by INT(11) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_tech_parts_sku (sku),
        KEY idx_tech_parts_category (category),
        KEY idx_tech_parts_stock (stock)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS tech_purchases (
        id INT(11) NOT NULL AUTO_INCREMENT,
        part_id INT(11) NOT NULL,
        quantity INT(11) NOT NULL,
        unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        supplier VARCHAR(150) DEFAULT NULL,
        reference_no VARCHAR(80) DEFAULT NULL,
        purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by INT(11) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_tech_purchases_part (part_id),
        KEY idx_tech_purchases_date (purchased_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS pc_builds (
        id INT(11) NOT NULL AUTO_INCREMENT,
        build_name VARCHAR(160) NOT NULL,
        customer_name VARCHAR(160) DEFAULT NULL,
        status ENUM('quoted','reserved','sold','cancelled') NOT NULL DEFAULT 'quoted',
        budget DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        notes TEXT DEFAULT NULL,
        created_by INT(11) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_pc_builds_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS pc_build_items (
        id INT(11) NOT NULL AUTO_INCREMENT,
        build_id INT(11) NOT NULL,
        part_id INT(11) NOT NULL,
        quantity INT(11) NOT NULL DEFAULT 1,
        sale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (id),
        KEY idx_pc_build_items_build (build_id),
        KEY idx_pc_build_items_part (part_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS customer_orders (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        status ENUM('paid','processing','completed','cancelled') NOT NULL DEFAULT 'paid',
        total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY idx_customer_orders_user (user_id), KEY idx_customer_orders_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS customer_order_items (
        id INT(11) NOT NULL AUTO_INCREMENT,
        order_id INT(11) NOT NULL,
        part_id INT(11) NOT NULL,
        quantity INT(11) NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (id), KEY idx_customer_order_items_order (order_id), KEY idx_customer_order_items_part (part_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function peso(float $amount): string {
    return 'PHP ' . number_format($amount, 2);
}

function build_status_label(string $status): string {
    $labels = [
        'quoted' => 'Quoted',
        'reserved' => 'Reserved',
        'sold' => 'Sold',
        'cancelled' => 'Cancelled',
    ];
    return $labels[$status] ?? ucfirst($status);
}
?>
