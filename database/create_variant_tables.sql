-- Create product_attribute_values table
CREATE TABLE IF NOT EXISTS product_attribute_values (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attribute_id BIGINT UNSIGNED NOT NULL,
    value VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (attribute_id) REFERENCES product_attributes(id) ON DELETE CASCADE
);

-- Create product_variants table
CREATE TABLE IF NOT EXISTS product_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(255) NULL,
    quantity INT DEFAULT 0,
    cost_price DECIMAL(10, 2) NULL,
    selling_price DECIMAL(10, 2) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_active (product_id, is_active)
);

-- Create product_variant_options table
CREATE TABLE IF NOT EXISTS product_variant_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    variant_id BIGINT UNSIGNED NOT NULL,
    attribute_value_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (attribute_value_id) REFERENCES product_attribute_values(id) ON DELETE CASCADE,
    UNIQUE KEY unique_variant_attribute (variant_id, attribute_value_id)
);

-- Create product_variant_barcodes table
CREATE TABLE IF NOT EXISTS product_variant_barcodes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    variant_id BIGINT UNSIGNED NOT NULL,
    barcode VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    INDEX idx_barcode (barcode)
);

-- Add variant_id to bill_product table
ALTER TABLE bill_product ADD COLUMN IF NOT EXISTS variant_id BIGINT UNSIGNED NULL AFTER product_id;
ALTER TABLE bill_product ADD CONSTRAINT fk_bill_product_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL;

-- Add variant_id to purchase_bill_product table
ALTER TABLE purchase_bill_product ADD COLUMN IF NOT EXISTS variant_id BIGINT UNSIGNED NULL AFTER product_id;
ALTER TABLE purchase_bill_product ADD CONSTRAINT fk_purchase_bill_product_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL;

-- Add variant_id to batches table
ALTER TABLE batches ADD COLUMN IF NOT EXISTS variant_id BIGINT UNSIGNED NULL AFTER product_id;
ALTER TABLE batches ADD CONSTRAINT fk_batches_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE;
