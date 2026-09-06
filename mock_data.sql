-- Sample inventory and PC builds for PRIME TechBuild.
-- Safe to run more than once: existing SKUs and build names are skipped.

START TRANSACTION;

INSERT IGNORE INTO tech_parts
    (sku, name, category, brand, model, socket, form_factor, wattage, stock, reorder_level, unit_cost, sale_price, supplier, notes)
VALUES
    ('CPU-R5-5600', 'Ryzen 5 5600', 'CPU', 'AMD', '100-100000927BOX', 'AM4', NULL, 65, 12, 3, 5200.00, 6499.00, 'PC Parts Hub', '6-core processor for value gaming builds'),
    ('CPU-I5-12400F', 'Core i5-12400F', 'CPU', 'Intel', 'BX8071512400F', 'LGA1700', NULL, 65, 8, 2, 6900.00, 8299.00, 'Northstar Components', 'Solid 6-core gaming and office CPU'),
    ('MB-B550M', 'B550M Pro-VDH WiFi', 'Motherboard', 'MSI', 'B550M PRO-VDH WIFI', 'AM4', 'mATX', NULL, 6, 2, 5100.00, 6299.00, 'PC Parts Hub', 'WiFi motherboard with PCIe 4.0 support'),
    ('MB-B760M', 'B760M DS3H AX', 'Motherboard', 'Gigabyte', 'B760M DS3H AX', 'LGA1700', 'mATX', NULL, 5, 2, 6500.00, 7699.00, 'Northstar Components', 'DDR5-ready board with WiFi'),
    ('RAM-16G-3200', '16GB DDR4 Memory Kit', 'Memory', 'Kingston', 'Fury Beast 2x8GB', NULL, 'DIMM', NULL, 20, 5, 1850.00, 2399.00, 'Memory Lane Supply', '3200MHz dual-channel kit'),
    ('RAM-32G-6000', '32GB DDR5 Memory Kit', 'Memory', 'TeamGroup', 'T-Force Vulcan 2x16GB', NULL, 'DIMM', NULL, 10, 3, 4550.00, 5599.00, 'Memory Lane Supply', '6000MHz kit for modern platforms'),
    ('SSD-1TB-NVME', '1TB NVMe SSD', 'Storage', 'WD', 'Blue SN580', NULL, 'M.2', NULL, 14, 4, 2800.00, 3499.00, 'Fast Storage Co.', 'PCIe 4.0 solid-state drive'),
    ('GPU-4060-8G', 'GeForce RTX 4060 8GB', 'Graphics Card', 'MSI', 'Ventus 2X Black OC', NULL, NULL, 115, 7, 2, 17500.00, 19999.00, 'Northstar Components', 'Efficient 1080p gaming graphics card'),
    ('GPU-7600-8G', 'Radeon RX 7600 8GB', 'Graphics Card', 'Sapphire', 'Pulse', NULL, NULL, 165, 4, 2, 15200.00, 17999.00, 'PC Parts Hub', 'Mainstream 1080p gaming graphics card'),
    ('PSU-650-BRONZE', '650W 80+ Bronze PSU', 'Power Supply', 'Cooler Master', 'MWE Bronze V2', NULL, 'ATX', 650, 9, 3, 2800.00, 3499.00, 'Power Grid Supplies', 'Reliable semi-modular power supply'),
    ('CASE-AIRFLOW', 'Airflow mATX Case', 'Case', 'Montech', 'Air 100 Lite', NULL, 'mATX', NULL, 6, 2, 2300.00, 2999.00, 'PC Parts Hub', 'Mesh-front compact case'),
    ('COOL-AG400', 'Tower CPU Cooler', 'CPU Cooler', 'DeepCool', 'AG400', NULL, 'ATX', 220, 10, 3, 1250.00, 1699.00, 'PC Parts Hub', 'Quiet tower cooler for AM4 and LGA1700');

INSERT INTO pc_builds (build_name, customer_name, status, budget, notes)
SELECT 'Starter 1080p Build', 'Walk-in Customer', 'quoted', 45000.00, 'Balanced entry gaming system for 1080p play.'
WHERE NOT EXISTS (SELECT 1 FROM pc_builds WHERE build_name = 'Starter 1080p Build');

INSERT INTO pc_builds (build_name, customer_name, status, budget, notes)
SELECT 'Creator Workstation', 'Mia Santos', 'quoted', 65000.00, 'Fast storage and extra memory for editing and design work.'
WHERE NOT EXISTS (SELECT 1 FROM pc_builds WHERE build_name = 'Creator Workstation');

INSERT INTO pc_builds (build_name, customer_name, status, budget, notes)
SELECT 'Office Upgrade Package', 'Cedar Accounting', 'quoted', 30000.00, 'Quiet, dependable workstations for office deployment.'
WHERE NOT EXISTS (SELECT 1 FROM pc_builds WHERE build_name = 'Office Upgrade Package');

INSERT INTO pc_build_items (build_id, part_id, quantity, sale_price)
SELECT b.id, p.id, 1, p.sale_price
FROM pc_builds b CROSS JOIN tech_parts p
WHERE b.build_name = 'Starter 1080p Build' AND p.sku IN ('CPU-R5-5600', 'MB-B550M', 'RAM-16G-3200', 'SSD-1TB-NVME', 'GPU-4060-8G', 'PSU-650-BRONZE', 'CASE-AIRFLOW', 'COOL-AG400')
  AND NOT EXISTS (SELECT 1 FROM pc_build_items bi WHERE bi.build_id = b.id AND bi.part_id = p.id);

INSERT INTO pc_build_items (build_id, part_id, quantity, sale_price)
SELECT b.id, p.id, 1, p.sale_price
FROM pc_builds b CROSS JOIN tech_parts p
WHERE b.build_name = 'Creator Workstation' AND p.sku IN ('CPU-I5-12400F', 'MB-B760M', 'RAM-32G-6000', 'SSD-1TB-NVME', 'GPU-7600-8G', 'PSU-650-BRONZE', 'CASE-AIRFLOW', 'COOL-AG400')
  AND NOT EXISTS (SELECT 1 FROM pc_build_items bi WHERE bi.build_id = b.id AND bi.part_id = p.id);

INSERT INTO pc_build_items (build_id, part_id, quantity, sale_price)
SELECT b.id, p.id, 1, p.sale_price
FROM pc_builds b CROSS JOIN tech_parts p
WHERE b.build_name = 'Office Upgrade Package' AND p.sku IN ('CPU-R5-5600', 'MB-B550M', 'RAM-16G-3200', 'SSD-1TB-NVME', 'PSU-650-BRONZE', 'CASE-AIRFLOW', 'COOL-AG400')
  AND NOT EXISTS (SELECT 1 FROM pc_build_items bi WHERE bi.build_id = b.id AND bi.part_id = p.id);

COMMIT;