SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS sonic DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE sonic;

CREATE TABLE category (
  id int(11) NOT NULL,
  code varchar(25) DEFAULT NULL,
  name varchar(100) NOT NULL,
  icon varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categoryrelationships (
  parent int(11) NOT NULL,
  child int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE entity (
  id int(11) NOT NULL,
  name varchar(150) NOT NULL,
  type int(11) DEFAULT NULL,
  description varchar(1000) DEFAULT NULL,
  img int(11) NOT NULL,
  iconx int(11) DEFAULT NULL,
  icony int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE entityancestors (
  entityid int(11) NOT NULL,
  ancestorid int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE feedback (
  id int(11) NOT NULL,
  text varchar(1000) NOT NULL,
  name varchar(69) DEFAULT NULL,
  contact varchar(69) DEFAULT NULL,
  path varchar(150) DEFAULT NULL,
  issue int(11) DEFAULT NULL,
  date datetime DEFAULT NULL,
  dismissed tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE issues (
  id int(11) NOT NULL,
  entity int(11) NOT NULL,
  type int(11) NOT NULL,
  issue varchar(5000) NOT NULL,
  sourceurl varchar(300) NOT NULL,
  startdate datetime NOT NULL,
  enddate datetime DEFAULT NULL,
  ongoing tinyint(4) DEFAULT NULL,
  contentwarning varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE issuetype (
  id int(11) NOT NULL,
  name varchar(45) NOT NULL,
  icon varchar(45) NOT NULL,
  color varchar(15) NOT NULL,
  showOnTop tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE relationships (
  parent int(11) NOT NULL,
  child int(11) NOT NULL,
  relationtype int(11) NOT NULL DEFAULT 1,
  asOfDate date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE synonym (
  entityid int(11) NOT NULL,
  synonym varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


ALTER TABLE category
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY id_UNIQUE (id);

ALTER TABLE categoryrelationships
  ADD KEY parent (parent),
  ADD KEY child (child);

ALTER TABLE entity
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY id_UNIQUE (id);
ALTER TABLE entity ADD FULLTEXT KEY name (name);

ALTER TABLE entityancestors
  ADD KEY entityid (entityid),
  ADD KEY ancestorid (ancestorid);

ALTER TABLE feedback
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY id_UNIQUE (id);

ALTER TABLE issues
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY id_UNIQUE (id);

ALTER TABLE issuetype
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY id_UNIQUE (id);

ALTER TABLE relationships
  ADD KEY parent (parent),
  ADD KEY child (child);

ALTER TABLE synonym
  ADD KEY entityid (entityid);


ALTER TABLE category
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE entity
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE feedback
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE issues
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE issuetype
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;
