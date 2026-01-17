-- Quick initialization script for giveaway_sys
-- This reflects the current schema (MySQL 8.0.x).

CREATE DATABASE IF NOT EXISTS giveaway_sys
  DEFAULT CHARACTER SET utf8mb3
  DEFAULT COLLATE utf8mb3_general_ci;

USE giveaway_sys;

DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS addresses;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  uid varchar(50) NOT NULL,
  pwdhash varchar(255) NOT NULL,
  qq varchar(20) NOT NULL,
  user_group enum('new','auto','owner') DEFAULT 'new',
  res_count int DEFAULT '0',
  get_count int DEFAULT '0',
  autoget tinyint(1) DEFAULT '0',
  verified tinyint(1) DEFAULT '0',
  disabled tinyint(1) DEFAULT '0',
  PRIMARY KEY (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE events (
  eid char(4) NOT NULL,
  name varchar(100) NOT NULL,
  due_date datetime DEFAULT NULL,
  start_at datetime DEFAULT NULL,
  total int NOT NULL,
  used int DEFAULT '0',
  send_date varchar(50) DEFAULT NULL,
  allow_group varchar(50) DEFAULT 'new,auto',
  autogroup tinyint(1) DEFAULT '0',
  choice_amount int DEFAULT '1',
  send_way varchar(50) DEFAULT 'post',
  xa_allow tinyint(1) DEFAULT '0',
  is_hidden tinyint(1) DEFAULT '0',
  PRIMARY KEY (eid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE INDEX idx_events_start_at ON events (start_at);

CREATE TABLE addresses (
  aid int NOT NULL AUTO_INCREMENT,
  uid varchar(50) DEFAULT NULL,
  name varchar(255) DEFAULT NULL,
  postcode varchar(20) DEFAULT NULL,
  addr text,
  phone varchar(20) DEFAULT NULL,
  is_default tinyint(1) DEFAULT '0',
  is_intl tinyint(1) DEFAULT '0',
  is_deleted tinyint(1) DEFAULT '0',
  PRIMARY KEY (aid),
  KEY uid (uid),
  CONSTRAINT addresses_ibfk_1 FOREIGN KEY (uid) REFERENCES users (uid)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3;

CREATE TABLE orders (
  oid varchar(20) NOT NULL,
  uid varchar(50) DEFAULT NULL,
  eid char(4) DEFAULT NULL,
  aid int DEFAULT NULL,
  choice int DEFAULT NULL,
  xa char(1) DEFAULT '0',
  is_auto tinyint(1) DEFAULT '0',
  state tinyint DEFAULT '0',
  logistics_no varchar(100) DEFAULT NULL,
  user_hidden tinyint(1) DEFAULT '0',
  PRIMARY KEY (oid),
  KEY uid (uid),
  KEY eid (eid),
  KEY fk_order_address (aid),
  CONSTRAINT fk_order_address FOREIGN KEY (aid) REFERENCES addresses (aid),
  CONSTRAINT orders_ibfk_1 FOREIGN KEY (uid) REFERENCES users (uid),
  CONSTRAINT orders_ibfk_2 FOREIGN KEY (eid) REFERENCES events (eid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
