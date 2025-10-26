-- =========================================================
-- SMARTANGLER DATABASE
-- =========================================================
CREATE DATABASE smartangler;
USE smartangler;

-- =========================================================
-- TABLE: USER
-- =========================================================
CREATE TABLE USER (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(100) NOT NULL,
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
('admin@smartangler.com','admin123','Admin John','0112233445','admin','active','admin.jpg'),
('user1@smartangler.com','user123','Ahmad Ali','0123344556','angler','active','ahmad.jpg'),
('user2@smartangler.com','user234','Tan Mei Ling','0198877665','angler','active','mei.jpg');

-- =========================================================
-- TABLE: TOURNAMENT
-- =========================================================
CREATE TABLE TOURNAMENT (
  tournament_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  tournament_title VARCHAR(100),
  tournament_date DATE,
  location VARCHAR(255),
  description TEXT,
  start_time TIME,
  end_time TIME,
  tournament_fee DECIMAL(10,2),
  max_participants INT,
  image VARCHAR(255),
  created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  status ENUM('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
  FOREIGN KEY (user_id) REFERENCES USER(user_id)
) ENGINE=InnoDB;

INSERT INTO TOURNAMENT (user_id, tournament_title, tournament_date, location, description, start_time, end_time, tournament_fee, max_participants, image, status)
VALUES
(1,'Sabah Fishing Championship','2025-12-15','Kota Kinabalu Jetty - https://goo.gl/maps/abcd1234','Annual fishing competition with great prizes.','07:00:00','17:00:00',50.00,50,'sabah_tournament.jpg','upcoming'),
(1,'Lake Angler Fest','2025-11-20','Taman Tasik Perdana - https://goo.gl/maps/efgh5678','Fun lake fishing event with multiple categories.','08:00:00','16:00:00',30.00,30,'lake_event.jpg','upcoming');

-- =========================================================
-- TABLE: FISHING_SPOT
-- =========================================================
CREATE TABLE FISHING_SPOT (
  spot_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT,
  latitude DECIMAL(10,8),
  longitude DECIMAL(11,8),
  spot_image VARCHAR(255),
  spot_status ENUM('available','booked','cancelled','maintenance') DEFAULT 'available',
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id)
) ENGINE=InnoDB;

INSERT INTO FISHING_SPOT (tournament_id, latitude, longitude, spot_image, spot_status)
VALUES
(1,5.98040000,116.07350000,'spot1.jpg','booked'),
(1,5.98220000,116.07410000,'spot2.jpg','available'),
(2,6.01500000,116.08000000,'spot3.jpg','available');

-- =========================================================
-- TABLE: TOURNAMENT_REGISTRATION
-- =========================================================
CREATE TABLE TOURNAMENT_REGISTRATION (
  registration_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT,
  user_id INT,
  spot_id INT,
  registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  payment_proof VARCHAR(255),
  bank_account_name VARCHAR(100),
  bank_account_number VARCHAR(50),
  qr_code_image VARCHAR(255),
  approval_status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  approved_date DATETIME,
  notes TEXT,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id),
  FOREIGN KEY (user_id) REFERENCES USER(user_id),
  FOREIGN KEY (spot_id) REFERENCES FISHING_SPOT(spot_id)
) ENGINE=InnoDB;

INSERT INTO TOURNAMENT_REGISTRATION (tournament_id, user_id, spot_id, payment_proof, bank_account_name, bank_account_number, qr_code_image, approval_status)
VALUES
(1,2,1,'payment1.jpg','Maybank - SmartAngler','1234567890','maybank_qr.png','approved'),
(2,3,2,'payment2.jpg','CIMB Bank - SmartAngler','9876543210','cimb_qr.png','pending');

-- =========================================================
-- TABLE: SPOT_BOOKING
-- =========================================================
CREATE TABLE SPOT_BOOKING (
  booking_id INT AUTO_INCREMENT PRIMARY KEY,
  spot_id INT,
  user_id INT,
  booking_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  booking_status ENUM('available','reserved','full','cancelled') DEFAULT 'reserved',
  notes TEXT,
  FOREIGN KEY (spot_id) REFERENCES FISHING_SPOT(spot_id),
  FOREIGN KEY (user_id) REFERENCES USER(user_id)
) ENGINE=InnoDB;

INSERT INTO SPOT_BOOKING (spot_id, user_id, booking_status)
VALUES
(1,2,'reserved'),
(2,3,'available');

-- =========================================================
-- TABLE: FISH_CATCH
-- =========================================================
CREATE TABLE FISH_CATCH (
  catch_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT,
  user_id INT,
  fish_species VARCHAR(100),
  fish_weight DECIMAL(5,2),
  catch_time TIME,
  catch_date DATE,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id),
  FOREIGN KEY (user_id) REFERENCES USER(user_id)
) ENGINE=InnoDB;

INSERT INTO FISH_CATCH (tournament_id, user_id, fish_species, fish_weight, catch_time, catch_date)
VALUES
(1,2,'Tuna',5.40,'09:30:00','2025-12-15'),
(1,2,'Barramundi',4.80,'11:10:00','2025-12-15'),
(2,3,'Tilapia',2.30,'10:15:00','2025-11-20');

-- =========================================================
-- TABLE: CATEGORY
-- =========================================================
CREATE TABLE CATEGORY (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL,
  description TEXT
) ENGINE=InnoDB;

INSERT INTO CATEGORY (category_name, description)
VALUES
('Heaviest Catch','Winner based on single fish with the highest weight'),
('Most Fish','Winner with the highest number of fish caught'),
('Longest Fish','Winner with the longest fish measurement');

-- =========================================================
-- TABLE: RESULT
-- =========================================================
CREATE TABLE RESULT (
  result_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT,
  user_id INT,
  catch_id INT,
  category_id INT,
  ranking_position INT,
  total_fish_count INT,
  result_status ENUM('ongoing','final') DEFAULT 'ongoing',
  last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id),
  FOREIGN KEY (user_id) REFERENCES USER(user_id),
  FOREIGN KEY (catch_id) REFERENCES FISH_CATCH(catch_id),
  FOREIGN KEY (category_id) REFERENCES CATEGORY(category_id)
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
  tournament_id INT,
  user_id INT,
  rating INT,
  review_text TEXT,
  review_image VARCHAR(255),
  review_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_anonymous BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id),
  FOREIGN KEY (user_id) REFERENCES USER(user_id)
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
  tournament_id INT,
  event_date DATE,
  event_title VARCHAR(100),
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id)
) ENGINE=InnoDB;

INSERT INTO CALENDAR (tournament_id, event_date, event_title)
VALUES
(1,'2025-12-15','Sabah Fishing Championship'),
(2,'2025-11-20','Lake Angler Fest');

-- =========================================================
-- TABLE: SAVED
-- =========================================================
CREATE TABLE SAVED (
  saved_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT,
  user_id INT,
  is_saved BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id),
  FOREIGN KEY (user_id) REFERENCES USER(user_id)
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
  title VARCHAR(255),
  message TEXT,
  sent_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES USER(user_id)
) ENGINE=InnoDB;

INSERT INTO NOTIFICATION (user_id, title, message)
VALUES
(2,'Registration Approved','Your registration for Sabah Fishing Championship has been approved!'),
(3,'Upcoming Tournament','Lake Angler Fest will begin soon. Prepare your gear!');

-- =========================================================
-- TABLE: SPONSOR
-- =========================================================
CREATE TABLE SPONSOR (
  sponsor_id INT AUTO_INCREMENT PRIMARY KEY,
  sponsor_name VARCHAR(100),
  sponsor_logo VARCHAR(255),
  sponsor_description TEXT,
  contact_phone VARCHAR(20),
  contact_email VARCHAR(100),
  sponsored_amount DECIMAL(10,2)
) ENGINE=InnoDB;

INSERT INTO SPONSOR (sponsor_name, sponsor_logo, sponsor_description, contact_phone, contact_email, sponsored_amount)
VALUES
('Shimano Malaysia','shimano_logo.jpg','Leading fishing gear sponsor.','0122233445','info@shimano.com',5000.00),
('Daiwa Sports','daiwa_logo.jpg','Supporting sustainable angling tournaments.','0177788990','contact@daiwa.com',3000.00);

-- =========================================================
-- TABLE: TOURNAMENT_PRIZE
-- =========================================================
CREATE TABLE TOURNAMENT_PRIZE (
  prize_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT,
  sponsor_id INT,
  prize_ranking VARCHAR(50),
  prize_description TEXT,
  prize_value DECIMAL(10,2),
  prize_update_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tournament_id) REFERENCES TOURNAMENT(tournament_id),
  FOREIGN KEY (sponsor_id) REFERENCES SPONSOR(sponsor_id)
) ENGINE=InnoDB;

INSERT INTO TOURNAMENT_PRIZE (tournament_id, sponsor_id, prize_ranking, prize_description, prize_value)
VALUES
(1,1,'1st','RM1000 + Shimano Reel + Trophy',1000.00),
(1,2,'2nd','RM500 + Daiwa Voucher',500.00),
(2,1,'1st','RM700 + Shimano Starter Pack',700.00);
