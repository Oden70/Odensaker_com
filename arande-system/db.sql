-- Grundläggande tabeller för ärendehanteringssystem (prefix: ahs_)

CREATE TABLE ahs_companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ahs_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    company_id INT,
    lang VARCHAR(10) DEFAULT 'sv',
    use_2fa TINYINT(1) DEFAULT 0,
    twofa_code VARCHAR(6),
    is_admin TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    role VARCHAR(20) DEFAULT 'user',
    FOREIGN KEY (company_id) REFERENCES ahs_companies(id)
);

CREATE TABLE ahs_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) DEFAULT 'open',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES ahs_companies(id),
    FOREIGN KEY (user_id) REFERENCES ahs_users(id)
);

-- Sätt en användare som superadmin (exempel):
-- UPDATE ahs_users SET role='superadmin' WHERE email='fredrik@odensaker.com';

-- Skapa superadmin-användare (lösenord: BytTillEgetLosenord)
-- Byt ut password_hash nedan mot en hash genererad med PHPs password_hash-funktion!
INSERT INTO ahs_users (email, password_hash, role, is_admin, lang)
VALUES (
    'fredrik@odensaker.com',
    '$2y$10$BytUtDennaHashTillEnRiktigHash', -- byt ut denna hash!
    'superadmin',
    1,
    'sv'
);

-- Exempel på att generera hash i PHP:
-- <?php echo password_hash('BytTillEgetLosenord', PASSWORD_DEFAULT); ?>
