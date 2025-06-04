-- Företag
CREATE TABLE boka_companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    logo VARCHAR(255),
    style_json TEXT,
    language VARCHAR(10) DEFAULT 'sv',
    org_number VARCHAR(32) NULL,
    address VARCHAR(255) NULL,
    zip_code VARCHAR(20) NULL,
    city VARCHAR(100) NULL,
    country VARCHAR(100) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(30) NULL
);

-- Användare
CREATE TABLE boka_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(30),
    address VARCHAR(255),
    zip_code VARCHAR(20),
    city VARCHAR(100),
    country VARCHAR(100),
    personal_number VARCHAR(32),
    avatar VARCHAR(255),
    notification_settings TEXT,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin','admin','booker','customer') NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    twofa_method ENUM('email','sms','both') DEFAULT 'email',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES boka_companies(id)
);

-- Meny
CREATE TABLE boka_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label_sv VARCHAR(255),
    label_en VARCHAR(255),
    page VARCHAR(255),
    min_role ENUM('superadmin','admin','booker','customer') NOT NULL,
    sort_order INT DEFAULT 0
);

-- Objekt (bokningsbara)
CREATE TABLE boka_objects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    FOREIGN KEY (company_id) REFERENCES boka_companies(id)
);

-- Tider
CREATE TABLE boka_times (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    object_id INT,
    start DATETIME,
    end DATETIME,
    booked_by INT,
    customer_id INT,
    note TEXT,
    event_id INT NULL,
    max_participants INT NULL,
    FOREIGN KEY (company_id) REFERENCES boka_companies(id),
    FOREIGN KEY (object_id) REFERENCES boka_objects(id),
    FOREIGN KEY (booked_by) REFERENCES boka_users(id),
    FOREIGN KEY (customer_id) REFERENCES boka_users(id)
);

-- Evenemang
CREATE TABLE boka_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    location VARCHAR(255) NULL,
    teacher_id INT NULL,
    max_participants INT NULL,
    price DECIMAL(10,2) NULL,
    price_type ENUM('per_tillfalle','hela_kursen') DEFAULT 'per_tillfalle',
    is_public TINYINT(1) DEFAULT 0,
    category_id INT NULL,
    image_url VARCHAR(255) NULL,
    extra_info TEXT NULL,
    FOREIGN KEY (company_id) REFERENCES boka_companies(id),
    FOREIGN KEY (teacher_id) REFERENCES boka_users(id),
    FOREIGN KEY (category_id) REFERENCES boka_categories(id)
);

CREATE TABLE IF NOT EXISTS boka_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

-- Skapa en superadmin-användare (byt ut värden efter behov)
INSERT INTO boka_users (
    company_id, first_name, last_name, username, email, phone, password_hash, role, status, twofa_method, created_at, updated_at
) VALUES (
    NULL, 'Fredrik', 'Odensåker', 'Oden70', 'fredrik@odensaker.com', '0722310149',
    '$2y$12$/zlOICpF0OCFTuXu9LDF6uYZEr/xY1gHaTMQAZzl0ZrSukqFBF.3S', -- byt ut mot hash av ditt lösenord
    'superadmin', 'active', 'email', NOW(), NOW()
);
