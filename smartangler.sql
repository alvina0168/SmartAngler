-- =========================================================
-- DATABASE INITIALIZATION - FIXED VERSION (NO SAMPLE DATA)
-- =========================================================
CREATE DATABASE IF NOT EXISTS smartangler;
USE smartangler;

-- =========================================================
-- TABLE: USER 
-- =========================================================
CREATE TABLE USER (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(100),
  phone_number VARCHAR(20),
  address TEXT,
  role ENUM('organizer','admin','angler') DEFAULT 'angler',
  created_by INT NULL COMMENT 'ID of organizer who created this admin',
  status ENUM('active','inactive') DEFAULT 'active',
  profile_image VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  reset_token VARCHAR(255) NULL,
  reset_expiry DATETIME NULL,
  FOREIGN KEY (created_by) REFERENCES USER(user_id) ON DELETE SET NULL,
  INDEX idx_role (role),
  INDEX idx_created_by (created_by)
) ENGINE=InnoDB;

-- =========================================================
-- TABLE: TOURNAMENT
-- =========================================================
CREATE TABLE TOURNAMENT (
  tournament_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  tournament_title VARCHAR(100) NOT NULL,
  tournament_date DATE NOT NULL,
  location VARCHAR(255),
  description TEXT,
  tournament_rules TEXT,
  start_time TIME,
  end_time TIME,
  tournament_fee DECIMAL(10,2) DEFAULT 0.00,
  max_participants INT DEFAULT 50,
  image VARCHAR(255),
  created_by INT,
  bank_account_number VARCHAR(50),
  bank_account_name VARCHAR(100),
  bank_account_holder VARCHAR(100),
  bank_qr VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  status ENUM('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
  FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES USER(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- TABLE: WEIGHING_STATION
-- =========================================================
CREATE TABLE WEIGHING_STATION (
  station_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  station_name VARCHAR(100) NOT NULL,
  marshal_name VARCHAR(100),
  status ENUM('active', 'inactive') DEFAULT 'active',
  notes TEXT,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- TABLE: ZONE 
-- =========================================================
CREATE TABLE ZONE (
  zone_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NULL,
  zone_name VARCHAR(100) NOT NULL,
  zone_description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- TABLE: FISHING_SPOT 
-- =========================================================
CREATE TABLE FISHING_SPOT (
  spot_id INT AUTO_INCREMENT PRIMARY KEY,
  zone_id INT NOT NULL,
  spot_number INT NOT NULL,
  latitude DECIMAL(10,8),
  longitude DECIMAL(11,8),
  spot_status ENUM('available','reserved','occupied','cancelled','maintenance') DEFAULT 'available',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES ZONE(zone_id) ON DELETE CASCADE,
  INDEX idx_zone_status (zone_id, spot_status)
) ENGINE=InnoDB;

-- =========================================================
-- TABLE: TOURNAMENT_REGISTRATION 
-- =========================================================
CREATE TABLE TOURNAMENT_REGISTRATION (
  registration_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  user_id INT NOT NULL,
  spot_id INT,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  phone_number VARCHAR(20) NOT NULL,
  emergency_contact VARCHAR(20),
  address TEXT,
  registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  payment_proof VARCHAR(255),
  approval_status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  approved_date DATETIME,
  rejection_reason TEXT,
  notes TEXT,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE,
  FOREIGN KEY (spot_id) REFERENCES FISHING_SPOT(spot_id) ON DELETE SET NULL,
  INDEX idx_approval_status (approval_status),
  INDEX idx_tournament_user (tournament_id, user_id)
) ENGINE=InnoDB;

-- =========================================================
-- TABLE: FISH_CATCH
-- =========================================================
CREATE TABLE FISH_CATCH (
  catch_id INT AUTO_INCREMENT PRIMARY KEY,
  station_id INT NOT NULL,
  registration_id INT NOT NULL,
  user_id INT NOT NULL,
  fish_species VARCHAR(100) NOT NULL,
  fish_weight DECIMAL(10,2) NOT NULL,
  catch_time DATETIME NOT NULL,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (station_id) REFERENCES WEIGHING_STATION(station_id) ON DELETE CASCADE,
  FOREIGN KEY (registration_id) REFERENCES TOURNAMENT_REGISTRATION(registration_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE,
  INDEX idx_station (station_id),
  INDEX idx_registration (registration_id),
  INDEX idx_user (user_id),
  INDEX idx_catch_time (catch_time),
  INDEX idx_weight (fish_weight)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- TABLE: CATEGORY
-- =========================================================
CREATE TABLE CATEGORY (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL,
  category_type ENUM('heaviest','lightest','most_catches','exact_weight','custom') DEFAULT 'custom',
  target_weight DECIMAL(10,2) NULL,
  number_of_ranking INT DEFAULT 3,
  description TEXT
) ENGINE=InnoDB;

-- Insert predefined categories (REQUIRED FOR SYSTEM TO WORK)
INSERT INTO CATEGORY (category_name, category_type, number_of_ranking, description) VALUES
('Heaviest Catch', 'heaviest', 3, 'Awarded to the participant who catches the heaviest fish during the tournament'),
('Lightest Catch', 'lightest', 3, 'Awarded to the participant who catches the lightest fish during the tournament'),
('Most Catches', 'most_catches', 3, 'Awarded to the participant with the highest number of catches'),
('Exact Weight Catch', 'exact_weight', 1, 'Awarded to the participant who catches a fish matching the exact target weight');

-- =========================================================
-- TABLE: RESULT
-- =========================================================
CREATE TABLE RESULT (
  result_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  user_id INT NOT NULL,
  catch_id INT,
  category_id INT NOT NULL,
  ranking_position INT,
  total_fish_count INT DEFAULT 0,
  result_status ENUM('ongoing','final') DEFAULT 'ongoing',
  last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE,
  FOREIGN KEY (catch_id) REFERENCES FISH_CATCH(catch_id) ON DELETE SET NULL,
  FOREIGN KEY (category_id) REFERENCES CATEGORY(category_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- TABLE: REVIEW
-- =========================================================
CREATE TABLE REVIEW (
  review_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  user_id INT NOT NULL,
  rating INT CHECK (rating >= 1 AND rating <= 5),
  review_text TEXT,
  review_image VARCHAR(255),
  review_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  admin_response TEXT NULL,
  response_date DATETIME NULL,
  is_anonymous BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- TABLE: CALENDAR
-- =========================================================
CREATE TABLE CALENDAR (
  event_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  event_date DATE,
  event_title VARCHAR(100),
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- TABLE: SAVED
-- =========================================================
CREATE TABLE SAVED (
  saved_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  user_id INT NOT NULL,
  is_saved BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE,
  UNIQUE KEY unique_save (tournament_id, user_id)
) ENGINE=InnoDB;

-- =========================================================
-- TABLE: NOTIFICATION
-- =========================================================
CREATE TABLE NOTIFICATION (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  tournament_id INT,
  title VARCHAR(255),
  message TEXT,
  sent_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  read_status TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = unread, 1 = read',
  FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE,
  INDEX idx_user_read (user_id, read_status)
) ENGINE=InnoDB;

-- =========================================================
-- TABLE: SPONSOR
-- =========================================================
CREATE TABLE SPONSOR (
  sponsor_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  sponsor_name VARCHAR(100) NOT NULL,
  sponsor_logo VARCHAR(255) DEFAULT NULL,
  contact_phone VARCHAR(20) DEFAULT NULL,
  contact_email VARCHAR(100) DEFAULT NULL,
  sponsor_description TEXT,
  sponsored_amount DECIMAL(10,2) DEFAULT 0.00,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE
 ) ENGINE=InnoDB;

-- =========================================================
-- TABLE: TOURNAMENT_PRIZE
-- =========================================================
CREATE TABLE TOURNAMENT_PRIZE (
  prize_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  category_id INT NOT NULL, 
  prize_ranking VARCHAR(20) NOT NULL,
  prize_description TEXT NOT NULL,
  prize_value DECIMAL(10,2) DEFAULT 0.00,
  target_weight DECIMAL(10,2) DEFAULT NULL, -- Added for exact-weight categories
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES CATEGORY(category_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- VIEW: v_spot_details (for admin reporting)
-- =========================================================
CREATE OR REPLACE VIEW v_spot_details AS
SELECT 
  fs.spot_id,
  fs.spot_number,
  fs.spot_status,
  z.zone_name,
  t.tournament_title,
  u.full_name AS angler_name
FROM FISHING_SPOT fs
JOIN ZONE z ON fs.zone_id = z.zone_id
LEFT JOIN TOURNAMENT t ON z.tournament_id = t.tournament_id
LEFT JOIN TOURNAMENT_REGISTRATION tr ON fs.spot_id = tr.spot_id
LEFT JOIN USER u ON tr.user_id = u.user_id;

COMMIT;
