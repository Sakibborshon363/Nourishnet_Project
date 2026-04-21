

CREATE DATABASE IF NOT EXISTS nourishnet_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE nourishnet_db;


CREATE TABLE IF NOT EXISTS users (
    user_id      INT AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(100)  NOT NULL,
    email        VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,          -- bcrypt hash
    role         ENUM('donor','volunteer','shelter') NOT NULL,
    phone        VARCHAR(20),
    address      TEXT,
    lat          DECIMAL(10,8) DEFAULT NULL,      -- GPS latitude
    lng          DECIMAL(11,8) DEFAULT NULL,      -- GPS longitude
    org_name     VARCHAR(150),                    -- Shelter/Donor org name
    is_active    TINYINT(1)    DEFAULT 1,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS food_listings (
    listing_id      INT AUTO_INCREMENT PRIMARY KEY,
    donor_id        INT           NOT NULL,
    title           VARCHAR(200)  NOT NULL,
    category        VARCHAR(50)   NOT NULL,        -- Cooked Meal, Bakery, etc.
    quantity        VARCHAR(100),
    serves          INT           DEFAULT 0,       -- আনুমানিক কতজনের খাবার
    pickup_address  TEXT,
    lat             DECIMAL(10,8) DEFAULT NULL,
    lng             DECIMAL(11,8) DEFAULT NULL,
    expires_at      DATETIME      DEFAULT NULL,    -- Pickup deadline
    status          ENUM('available','claimed','delivered') DEFAULT 'available',
    notes           TEXT,                          -- Allergens, instructions
    is_urgent       TINYINT(1)    DEFAULT 0,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_donor  (donor_id)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS rescues (
    rescue_id       INT AUTO_INCREMENT PRIMARY KEY,
    listing_id      INT  NOT NULL,
    volunteer_id    INT  NOT NULL,
    shelter_id      INT  DEFAULT NULL,             -- Destination shelter
    claimed_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    picked_up_at    TIMESTAMP NULL DEFAULT NULL,
    delivered_at    TIMESTAMP NULL DEFAULT NULL,
    status          ENUM('claimed','picked_up','delivered') DEFAULT 'claimed',
    volunteer_notes TEXT,
    FOREIGN KEY (listing_id)   REFERENCES food_listings(listing_id) ON DELETE CASCADE,
    FOREIGN KEY (volunteer_id) REFERENCES users(user_id),
    FOREIGN KEY (shelter_id)   REFERENCES users(user_id),
    INDEX idx_volunteer (volunteer_id),
    INDEX idx_status    (status)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS impact_logs (
    log_id        INT AUTO_INCREMENT PRIMARY KEY,
    rescue_id     INT  NOT NULL,
    meals_count   INT  DEFAULT 0,
    food_kg       DECIMAL(8,2) DEFAULT 0.00,
    co2_saved_kg  DECIMAL(8,2) DEFAULT 0.00,     -- 2.5 kg CO2 per kg food saved
    logged_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rescue_id) REFERENCES rescues(rescue_id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS shelter_requests (
    request_id    INT AUTO_INCREMENT PRIMARY KEY,
    shelter_id    INT  NOT NULL,
    food_type     VARCHAR(100),
    portions_needed INT DEFAULT 0,
    needed_by     DATE,
    notes         TEXT,
    status        ENUM('open','matched','fulfilled') DEFAULT 'open',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shelter_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;


INSERT INTO users (full_name, email, password_hash, role, phone, address, lat, lng, org_name) VALUES
('Rahim Ahmed',     'donor@test.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'donor',     '01711111111', 'Gulshan, Dhaka',     23.7937, 90.4066, 'Green Leaf Café'),
('Karim Hossain',   'volunteer@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'volunteer',  '01722222222', 'Dhanmondi, Dhaka',  23.7461, 90.3742, NULL),
('Hope Haven Org',  'shelter@test.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'shelter',    '01733333333', 'Mirpur, Dhaka',     23.8041, 90.3668, 'Hope Haven Shelter');

INSERT INTO food_listings (donor_id, title, category, quantity, serves, pickup_address, lat, lng, status, notes, is_urgent) VALUES
(1, 'Chicken Biryani (50 portions)',  'Cooked Meal', '50 portions', 50, 'Green Leaf Café, Gulshan-2', 23.7937, 90.4066, 'available', 'No peanuts. Pickup before 8pm.', 0),
(1, 'Assorted Pastries & Croissants', 'Baked Goods', '40 pieces',   25, 'Green Leaf Café, Gulshan-2', 23.7937, 90.4066, 'claimed',   'Best before tomorrow morning.',  1),
(1, 'Mixed Vegetable Rice',           'Cooked Meal', '30 portions', 30, 'Green Leaf Café, Gulshan-2', 23.7937, 90.4066, 'delivered', 'Vegetarian, no onion.',           0);

INSERT INTO rescues (listing_id, volunteer_id, shelter_id, status, delivered_at) VALUES
(2, 2, 3, 'claimed',   NULL),
(3, 2, 3, 'delivered', NOW());

INSERT INTO impact_logs (rescue_id, meals_count, food_kg, co2_saved_kg) VALUES
(2, 30, 15.00, 37.50);
