-- Lägg till nya kolumner och tabeller om de inte redan finns

-- Lägg till företagsuppgifter om de saknas
ALTER TABLE boka_companies
    ADD COLUMN IF NOT EXISTS org_number VARCHAR(32) NULL,
    ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS zip_code VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS country VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL;

-- Lägg till kolumner för event om de saknas
ALTER TABLE boka_events
    ADD COLUMN IF NOT EXISTS location VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS teacher_id INT NULL,
    ADD COLUMN IF NOT EXISTS max_participants INT NULL,
    ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NULL,
    ADD COLUMN IF NOT EXISTS price_type ENUM('per_tillfalle','hela_kursen') DEFAULT 'per_tillfalle',
    ADD COLUMN IF NOT EXISTS is_public TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS category_id INT NULL,
    ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS extra_info TEXT NULL,
    ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS tags VARCHAR(255) NULL;

-- Lägg till kategoritabell om den saknas
CREATE TABLE IF NOT EXISTS boka_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

-- Lägg till event_id och max_participants i boka_times om de saknas
ALTER TABLE boka_times
    ADD COLUMN IF NOT EXISTS event_id INT NULL,
    ADD COLUMN IF NOT EXISTS max_participants INT NULL;

-- Lägg till foreign keys om de saknas
ALTER TABLE boka_events
    ADD FOREIGN KEY (teacher_id) REFERENCES boka_users(id),
    ADD FOREIGN KEY (category_id) REFERENCES boka_categories(id);

ALTER TABLE boka_times
    ADD FOREIGN KEY (event_id) REFERENCES boka_events(id);

-- Lägg till språk om det saknas
ALTER TABLE boka_companies
    ADD COLUMN IF NOT EXISTS language VARCHAR(10) DEFAULT 'sv';

-- Lägg till notification_settings om det saknas
ALTER TABLE boka_users
    ADD COLUMN IF NOT EXISTS notification_settings TEXT;

-- Lägg till avatar om det saknas
ALTER TABLE boka_users
    ADD COLUMN IF NOT EXISTS avatar VARCHAR(255);

-- Lägg till twofa_method om det saknas
ALTER TABLE boka_users
    ADD COLUMN IF NOT EXISTS twofa_method ENUM('email','sms','both') DEFAULT 'email';

-- Lägg till updated_at om det saknas
ALTER TABLE boka_users
    ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Lägg till created_at om det saknas
ALTER TABLE boka_users
    ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP;

-- Lägg till status om det saknas
ALTER TABLE boka_users
    ADD COLUMN IF NOT EXISTS status ENUM('active','inactive') NOT NULL DEFAULT 'active';

-- Lägg till company_id om det saknas
ALTER TABLE boka_categories ADD COLUMN IF NOT EXISTS company_id INT NULL;

-- Lägg till foreign key om det saknas
ALTER TABLE boka_categories ADD CONSTRAINT IF NOT EXISTS fk_categories_company FOREIGN KEY (company_id) REFERENCES boka_companies(id);

-- Om du vill ha spårning av när kategorin skapades/uppdaterades:
ALTER TABLE boka_categories
    ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
