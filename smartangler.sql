-- =========================================================
-- DATABASE INITIALIZATION
-- =========================================================
CREATE DATABASE smartangler;
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
  role ENUM('admin','angler') DEFAULT 'angler',
  status ENUM('active','inactive') DEFAULT 'active',
  profile_image VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO USER (email, password, full_name, phone_number, role, status, profile_image)
VALUES
('admin@smartangler.com','admin123','Admin John','0112233445','admin','active','profile1.png'),
('user1@smartangler.com','user123','Ahmad Ali','0123344556','angler','active','profile2.jpg'),
('user2@smartangler.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Tan Mei Ling','0198877665','angler','active','profile3.jpg');

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
-- INSERT TOURNAMENT DATA
-- =========================================================
INSERT INTO TOURNAMENT (
  user_id, tournament_title, tournament_date, location, description,
  start_time, end_time, tournament_fee, max_participants, image, status,
  created_by, bank_account_number, bank_account_name, bank_account_holder
) VALUES
(1, 'Sabah Fishing Championship', '2025-10-27', 'Kota Kinabalu Jetty - https://goo.gl/maps/abcd1234',
 'Annual fishing competition with great prizes.', '07:00:00', '17:00:00',
 50.00, 50, 'pond2.jpg', 'ongoing', 1, '1234567890', 'Maybank', 'Admin John'),
(1, 'Lake Angler Fest', '2025-11-20', 'Taman Tasik Perdana - https://goo.gl/maps/efgh5678',
 'Fun lake fishing event with multiple categories.', '08:00:00', '16:00:00',
 30.00, 30, 'pond2.jpg', 'upcoming', 1, '9876543210', 'CIMB', 'Admin John');
  
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

-- =========================================================
-- INSERT WEIGHING_STATION DATA
-- =========================================================
INSERT INTO WEIGHING_STATION (tournament_id, station_name, marshal_name, status, notes) VALUES
(1, 'S1', 'John Doe', 'active', 'Main station at north jetty'),
(1, 'S2', 'Jane Smith', 'active', 'Secondary station at south dock'),
(1, 'S3', 'Mike Johnson', 'active', 'Backup station near parking area'),
(2, 'Station A', 'Sarah Lee', 'active', 'East side of the lake'),
(2, 'Station B', 'David Wong', 'active', 'West side near pavilion');

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
(1, 'Zone B', 'East dock fishing zone.'),
(2, 'Zone Alpha', 'North lake side.'),
(2, 'Zone Beta', 'Shady corner area.');

-- =========================================================
-- TABLE: FISHING_SPOT
-- =========================================================
CREATE TABLE FISHING_SPOT (
  spot_id INT AUTO_INCREMENT PRIMARY KEY,
  zone_id INT NOT NULL,
  latitude DECIMAL(10,8),
  longitude DECIMAL(11,8),
  spot_status ENUM('available','booked','cancelled','maintenance') DEFAULT 'available',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES ZONE(zone_id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO FISHING_SPOT (zone_id, latitude, longitude, spot_status)
VALUES
(1, 5.98765432, 116.07654321, 'available'),
(1, 5.98765499, 116.07654400, 'booked'),
(2, 5.98800000, 116.07700000, 'available'),
(3, 3.13400000, 101.68500000, 'maintenance');

-- =========================================================
-- TABLE: TOURNAMENT_REGISTRATION
-- =========================================================
CREATE TABLE TOURNAMENT_REGISTRATION (
  registration_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  user_id INT NOT NULL,
  spot_id INT,
  registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  payment_proof VARCHAR(255),
  approval_status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  approved_date DATETIME,
  notes TEXT,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE,
  FOREIGN KEY (spot_id) REFERENCES FISHING_SPOT(spot_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO TOURNAMENT_REGISTRATION (tournament_id, user_id, spot_id, payment_proof, approval_status, approved_date)
VALUES
(1,2,1,'payment1.jpg','approved','2025-10-20 10:30:00'),
(2,3,3,'payment2.jpg','pending',NULL);

-- =========================================================
-- TABLE: FISH_CATCH
-- =========================================================
CREATE TABLE FISH_CATCH (
    catch_id INT PRIMARY KEY AUTO_INCREMENT,
    station_id INT NOT NULL,
    user_id INT NOT NULL,
    fish_species VARCHAR(100) NOT NULL,
    fish_weight DECIMAL(10,2) NOT NULL,
    catch_time DATETIME NOT NULL,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES WEIGHING_STATION(station_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE,
    INDEX idx_station (station_id),
    INDEX idx_user (user_id),
    INDEX idx_catch_time (catch_time),
    INDEX idx_weight (fish_weight)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO FISH_CATCH (station_id, user_id, fish_species, fish_weight, catch_time, notes) VALUES
-- Tournament 1 - Station S1
(1, 2, 'ALUR ALUR', 5.50, '2025-10-27 08:30:00', 'Biggest catch of the morning session'),
(1, 2, 'IKAN MSIN', 3.25, '2025-10-27 10:15:00', 'Caught near the mangroves'),
-- Tournament 1 - Station S2
(2, 2, 'BAWAL', 6.20, '2025-10-27 11:30:00', 'Caught using live bait'),
(2, 2, 'JENAHAK', 2.90, '2025-10-27 14:20:00', NULL),

-- Tournament 2 - Station A
(4, 3, 'TILAPIA', 2.30, '2025-11-20 10:15:00', 'Good quality tilapia'),
(4, 3, 'KELAH', 3.80, '2025-11-20 12:30:00', NULL),
-- Tournament 2 - Station B
(5, 3, 'KELI', 1.90, '2025-11-20 10:45:00', NULL),
(5, 3, 'TOMAN', 6.40, '2025-11-20 14:00:00', 'Biggest catch at Station B');

-- =========================================================
-- TABLE: CATEGORY
-- =========================================================
CREATE TABLE CATEGORY (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL,
  number_of_ranking INT DEFAULT 3,
  description TEXT
) ENGINE=InnoDB;

INSERT INTO CATEGORY (category_name, number_of_ranking, description)
VALUES
('Heaviest Catch',3,'Winner based on single fish with the highest weight'),
('Most Fish',3,'Winner with the highest number of fish caught'),
('Longest Fish',3,'Winner with the longest fish measurement');

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

INSERT INTO RESULT (tournament_id, user_id, catch_id, category_id, ranking_position, total_fish_count, result_status)
VALUES
(1,2,1,1,1,2,'final'),
(2,3,3,2,2,1,'ongoing');

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
  is_anonymous BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO REVIEW (tournament_id, user_id, rating, review_text, review_image, is_anonymous)
VALUES
(1,2,5,'Amazing event! Great prizes.','review1.jpg',FALSE),
(2,3,4,'Good experience, but weather was hot.','review2.jpg',TRUE);

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

INSERT INTO SAVED (tournament_id, user_id, is_saved)
VALUES
(1,2,TRUE),
(2,3,TRUE);

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
-- SAMPLE NOTIFICATION DATA (Working Examples)
-- =========================================================

-- Example 1: Unread notification (default read_status = 0)
INSERT INTO NOTIFICATION (user_id, tournament_id, title, message)
VALUES
(2, 1, 'Registration Approved', 'Your registration for the Sabah Fishing Championship has been approved!');


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

INSERT INTO SPONSOR (tournament_id, sponsor_name, sponsor_logo, sponsor_description, contact_phone, contact_email, sponsored_amount)
VALUES
(1,'Shimano Malaysia','shimano_logo.jpg','Leading fishing gear sponsor.','0122233445','info@shimano.com',5000.00),
(2,'Daiwa Sports','daiwa_logo.jpg','Supporting sustainable angling tournaments.','0177788990','contact@daiwa.com',3000.00);

-- =========================================================
-- TABLE: TOURNAMENT_PRIZE
-- =========================================================
CREATE TABLE TOURNAMENT_PRIZE (
  prize_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  sponsor_id INT,
  prize_ranking VARCHAR(20),
  prize_description TEXT,
  prize_value DECIMAL(10,2),
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id) ON DELETE CASCADE,
  FOREIGN KEY (sponsor_id) REFERENCES SPONSOR(sponsor_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO TOURNAMENT_PRIZE (tournament_id, sponsor_id, prize_ranking, prize_description, prize_value)
VALUES
(1,1,'1st','RM1000 + Shimano Reel + Trophy',1000.00),
(1,2,'2nd','RM500 + Daiwa Voucher',500.00),
(2,2,'1st','RM700 + Shimano Starter Pack',700.00);

-- =========================================================
-- VIEW: v_spot_details (for admin reporting)
-- =========================================================
CREATE OR REPLACE VIEW v_spot_details AS
SELECT 
  fs.spot_id,
  fs.spot_status,
  z.zone_name,
  t.tournament_title,
  u.full_name AS angler_name
FROM FISHING_SPOT fs
JOIN ZONE z ON fs.zone_id = z.zone_id
JOIN TOURNAMENT t ON z.tournament_id = t.tournament_id
LEFT JOIN TOURNAMENT_REGISTRATION tr ON fs.spot_id = tr.spot_id
LEFT JOIN USER u ON tr.user_id = u.user_id;

