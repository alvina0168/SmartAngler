-- =========================================================
-- DATABASE INITIALIZATION - FIXED VERSION
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
  role ENUM('admin','angler') DEFAULT 'angler',
  status ENUM('active','inactive') DEFAULT 'active',
  profile_image VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  reset_token VARCHAR(255) NULL,
  reset_expiry DATETIME NULL
) ENGINE=InnoDB;

INSERT INTO USER (email, password, full_name, phone_number, role, status, profile_image)
VALUES
('admin@smartangler.com','admin123','Admin John','0112233445','admin','active','profile1.png'),
('user1@smartangler.com','user123','Ahmad Ali','0123344556','angler','active','profile2.png'),
('alvinaao0168@gmail.com','nana2003','Alvina Alphonsus','0178373970','angler','active','profile.png');

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

INSERT INTO TOURNAMENT (
  user_id, tournament_title, tournament_date, location, description, tournament_rules,
  start_time, end_time, tournament_fee, max_participants, image,
  created_by, bank_account_number, bank_account_name, bank_account_holder, bank_qr 
) VALUES
(1, 'Sabah Fishing Championship', '2025-10-27', 'Kota Kinabalu Jetty - https://goo.gl/maps/abcd1234',
 'Join us for the biggest fishing tournament in Sabah! Test your skills against the best anglers in the region.',
 '1. Participants must catch fish using legal fishing rods and techniques only
2. No nets, traps, or outside help is allowed
3. All catches must be weighed at official weighing stations
4. Fish must be kept alive until weighed
5. Competition time: 7:00 AM - 5:00 PM
6. Late arrivals will be disqualified
7. Winners will be announced at 6:00 PM', 
 '07:00:00', '17:00:00', 50.00, 50, 'pond1.jpg', 1, '1234567890', 'Maybank', 'Admin John', 'QR1.jpg'),

(1, 'Lake Angler Fest', '2025-11-20', 'Taman Tasik Perdana - https://goo.gl/maps/efgh5678',
 'Family-friendly fishing festival with prizes for all age categories. Bring your family and enjoy a day of fishing!',
 '1. Open to all ages and skill levels
2. Use only rods and reels - no nets allowed
3. Catch and release policy for endangered species
4. All participants must register at least 30 minutes before start time
5. Children under 12 must be accompanied by an adult
6. Keep the lake clean - dispose of waste properly
7. Have fun and practice good sportsmanship!', 
 '08:00:00', '16:00:00', 30.00, 30, 'pond2.jpg', 1, '9876543210', 'CIMB', 'Admin John', 'QR1.jpg');
-- =========================================================
-- TABLE: WEIGHING_STATION
-- =========================================================
CREATE TABLE WEIGHING_STATION (
    station_id INT PRIMARY KEY AUTO_INCREMENT,
    tournament_id INT NOT NULL,
    station_name VARCHAR(100) NOT NULL,
    marshal_name VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    notes TEXT,
    FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO WEIGHING_STATION (tournament_id, station_name, marshal_name, notes) VALUES
(1, 'S1', 'John Doe', 'Main station KRPV'),
(1, 'S2', 'Jane Smith', 'Secondary station KRPV'),
(2, 'Station A', 'Sarah Lee', 'East side of the lake'),
(2, 'Station B', 'David Wong','West side near pavilion');

-- =========================================================
-- TABLE: ZONE 
-- =========================================================
CREATE TABLE ZONE (
  zone_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NULL,
  zone_name VARCHAR(100) NOT NULL,
  zone_description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tournament_id)
    REFERENCES TOURNAMENT(tournament_id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO ZONE (tournament_id, zone_name, zone_description)
VALUES
(1, 'Zone A', 'Main jetty area with 20 spots.'),
(1, 'Zone B', 'East dock fishing zone.');

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

-- Insert sample fishing spots for Zone A (zone_id = 1)
INSERT INTO FISHING_SPOT (zone_id, spot_number, latitude, longitude, spot_status) VALUES
(1, 1, 5.62071000, 116.32570010, 'available'),
(1, 2, 5.62066247, 116.32572055, 'available'),
(1, 3, 5.62062510, 116.32573128, 'available'),
(1, 4, 5.62058240, 116.32574469, 'available'),
(1, 5, 5.62055303, 116.32575542, 'available'),
(1, 6, 5.62051566, 116.32576883, 'available'),
(1, 7, 5.62046495, 116.32577688, 'available'),
(1, 8, 5.62042491, 116.32578492, 'available'),
(1, 9, 5.62037953, 116.32580906, 'available'),
(1, 10, 5.62033949, 116.32582784, 'available');

-- Insert sample fishing spots for Zone B (zone_id = 2)
INSERT INTO FISHING_SPOT (zone_id, spot_number, latitude, longitude, spot_status) VALUES
(2, 1, 5.62118833, 116.32595658, 'available'),
(2, 2, 5.62122837, 116.32597804, 'available'),
(2, 3, 5.62127642, 116.32599682, 'available'),
(2, 4, 5.62131646, 116.32600754, 'available'),
(2, 5, 5.62135917, 116.32602364, 'available'),
(2, 6, 5.62141255, 116.32604778, 'available'),
(2, 7, 5.62146861, 116.32606924, 'available'),
(2, 8, 5.62151665, 116.32609069, 'available'),
(2, 9, 5.62156203, 116.32610947, 'available'),
(2, 10, 5.62160207, 116.32612556, 'available');

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
    catch_id INT PRIMARY KEY AUTO_INCREMENT,
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
  category_type ENUM(
    'heaviest',
    'lightest',
    'most_catches',
    'exact_weight',
    'custom'
  ) DEFAULT 'custom',
  target_weight DECIMAL(10,2) NULL,
  number_of_ranking INT DEFAULT 3,
  description TEXT
) ENGINE=InnoDB;

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

INSERT INTO CALENDAR (tournament_id, event_date, event_title)
VALUES
(1,'2025-10-27','Sabah Fishing Championship'),
(2,'2025-11-20','Lake Angler Fest');

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
  tournament_id INT,
  sponsor_name VARCHAR(100),
  sponsor_logo VARCHAR(255),
  sponsor_description TEXT,
  contact_phone VARCHAR(20),
  contact_email VARCHAR(100),
  sponsored_amount DECIMAL(10,2),
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- TABLE: TOURNAMENT_PRIZE
-- =========================================================
CREATE TABLE TOURNAMENT_PRIZE (
  prize_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  sponsor_id INT,
  category_id INT, 
  prize_ranking VARCHAR(20),
  prize_description TEXT,
  prize_value DECIMAL(10,2),
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE,
  FOREIGN KEY (sponsor_id) REFERENCES SPONSOR(sponsor_id) ON DELETE SET NULL,
  FOREIGN KEY (category_id) REFERENCES CATEGORY(category_id) ON DELETE SET NULL
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
JOIN TOURNAMENT t ON z.tournament_id = t.tournament_id
LEFT JOIN TOURNAMENT_REGISTRATION tr ON fs.spot_id = tr.spot_id
LEFT JOIN USER u ON tr.user_id = u.user_id;

-- =========================================================
-- CREATE DIRECTORY FOR PAYMENT PROOFS
-- =========================================================
-- IMPORTANT: Manually create this directory:
-- /assets/images/payments/
-- Set permissions: chmod 777 /assets/images/payments/

COMMIT;

