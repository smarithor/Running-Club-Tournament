-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 21, 2025 at 02:55 PM
-- Server version: 10.11.6-MariaDB-0+deb12u1-log
-- PHP Version: 8.1.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `66087_club_tournament`
--

-- --------------------------------------------------------

--
-- Table structure for table `Fencer`
--

CREATE TABLE `Fencer` (
  `ID` int(11) NOT NULL,
  `FullName` text DEFAULT NULL,
  `Name` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `RankChangeLog`
--

CREATE TABLE `RankChangeLog` (
  `ID` int(11) NOT NULL,
  `FightID` int(11) NOT NULL,
  `FencerID` int(11) NOT NULL,
  `OldRank` int(11) NOT NULL,
  `NewRank` int(11) NOT NULL,
  `ChangeDateTime` datetime NOT NULL,
  `TournamentID` int(11) NOT NULL,
  `Comment` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Season`
--

CREATE TABLE `Season` (
  `ID` int(11) NOT NULL,
  `Name` text DEFAULT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Tournament`
--

CREATE TABLE `Tournament` (
  `ID` int(11) NOT NULL,
  `TournamentTypeID` int(11) DEFAULT NULL,
  `SeasonID` int(11) DEFAULT NULL,
  `Name` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentFencer`
--

CREATE TABLE `TournamentFencer` (
  `ID` int(11) NOT NULL,
  `TournamentID` int(11) DEFAULT NULL,
  `FencerID` int(11) DEFAULT NULL,
  `Rank` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentMatch`
--

CREATE TABLE `TournamentMatch` (
  `ID` int(11) NOT NULL,
  `TournamentID` int(11) DEFAULT NULL,
  `ChallengerID` int(11) DEFAULT NULL,
  `ChallengedID` int(11) DEFAULT NULL,
  `FightDate` date DEFAULT NULL,
  `ChallengerScore` int(11) DEFAULT NULL,
  `ChallangedScore` int(11) DEFAULT NULL,
  `ChallengerWarnings` int(11) DEFAULT NULL,
  `ChallengedWarnings` int(11) DEFAULT NULL,
  `Doubles` int(11) DEFAULT NULL,
  `ChallengerInitalRank` int(11) DEFAULT NULL,
  `ChallangedInitalRank` int(11) DEFAULT NULL,
  `Judge` int(11) DEFAULT NULL,
  `Referee1` int(11) DEFAULT NULL,
  `Referee2` int(11) DEFAULT NULL,
  `MatchTable` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TournamentType`
--

CREATE TABLE `TournamentType` (
  `ID` int(11) NOT NULL,
  `Name` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vwTournamentFencer`
-- (See below for the actual view)
--
CREATE TABLE `vwTournamentFencer` (
`TournamentID` int(11)
,`TournamentName` text
,`FencerID` int(11)
,`FencerName` text
,`Rank` int(11)
,`RankChange` varchar(1)
,`LastFightID` int(11)
);

-- --------------------------------------------------------

--
-- Structure for view `vwTournamentFencer`
--
DROP TABLE IF EXISTS `vwTournamentFencer`;

CREATE ALGORITHM=UNDEFINED DEFINER=`66087_club_tour_admin`@`localhost` SQL SECURITY DEFINER VIEW `vwTournamentFencer`  AS SELECT `TF`.`TournamentID` AS `TournamentID`, `TM`.`Name` AS `TournamentName`, `TF`.`FencerID` AS `FencerID`, `FE`.`Name` AS `FencerName`, `TF`.`Rank` AS `Rank`, `LA`.`RankChange` AS `RankChange`, `LA`.`FightID` AS `LastFightID` FROM (((`TournamentFencer` `TF` join `Tournament` `TM` on(`TF`.`TournamentID` = `TM`.`ID`)) join `Fencer` `FE` on(`TF`.`FencerID` = `FE`.`ID`)) left join (select `RankChangeLog`.`TournamentID` AS `TournamentID`,`RankChangeLog`.`FightID` AS `FightID`,`RankChangeLog`.`FencerID` AS `FencerID`,`RankChangeLog`.`OldRank` AS `OldRank`,`RankChangeLog`.`NewRank` AS `NewRank`,case when `RankChangeLog`.`OldRank` > `RankChangeLog`.`NewRank` then '+' else '-' end AS `RankChange` from `RankChangeLog` where `RankChangeLog`.`ID` in (select max(`RankChangeLog`.`ID`) from `RankChangeLog` group by `RankChangeLog`.`TournamentID`,`RankChangeLog`.`FencerID`)) `LA` on(`TF`.`TournamentID` = `LA`.`TournamentID` and `TF`.`FencerID` = `LA`.`FencerID`))  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Fencer`
--
ALTER TABLE `Fencer`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `RankChangeLog`
--
ALTER TABLE `RankChangeLog`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `Season`
--
ALTER TABLE `Season`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `Tournament`
--
ALTER TABLE `Tournament`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `TournamentTypeID` (`TournamentTypeID`),
  ADD KEY `SeasonID` (`SeasonID`);

--
-- Indexes for table `TournamentFencer`
--
ALTER TABLE `TournamentFencer`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `TournamentID` (`TournamentID`),
  ADD KEY `FencerID` (`FencerID`);

--
-- Indexes for table `TournamentMatch`
--
ALTER TABLE `TournamentMatch`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `ChallengerID` (`ChallengerID`),
  ADD KEY `ChallengedID` (`ChallengedID`),
  ADD KEY `Judge` (`Judge`),
  ADD KEY `Referee1` (`Referee1`),
  ADD KEY `Referee2` (`Referee2`),
  ADD KEY `TournamentID` (`TournamentID`);

--
-- Indexes for table `TournamentType`
--
ALTER TABLE `TournamentType`
  ADD PRIMARY KEY (`ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Fencer`
--
ALTER TABLE `Fencer`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `RankChangeLog`
--
ALTER TABLE `RankChangeLog`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Season`
--
ALTER TABLE `Season`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Tournament`
--
ALTER TABLE `Tournament`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `TournamentFencer`
--
ALTER TABLE `TournamentFencer`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `TournamentMatch`
--
ALTER TABLE `TournamentMatch`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `TournamentType`
--
ALTER TABLE `TournamentType`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Tournament`
--
ALTER TABLE `Tournament`
  ADD CONSTRAINT `Tournament_ibfk_1` FOREIGN KEY (`TournamentTypeID`) REFERENCES `TournamentType` (`ID`),
  ADD CONSTRAINT `Tournament_ibfk_2` FOREIGN KEY (`SeasonID`) REFERENCES `Season` (`ID`);

--
-- Constraints for table `TournamentFencer`
--
ALTER TABLE `TournamentFencer`
  ADD CONSTRAINT `TournamentFencer_ibfk_1` FOREIGN KEY (`TournamentID`) REFERENCES `Tournament` (`ID`),
  ADD CONSTRAINT `TournamentFencer_ibfk_2` FOREIGN KEY (`FencerID`) REFERENCES `Fencer` (`ID`);

--
-- Constraints for table `TournamentMatch`
--
ALTER TABLE `TournamentMatch`
  ADD CONSTRAINT `TournamentMatch_ibfk_1` FOREIGN KEY (`ChallengerID`) REFERENCES `Fencer` (`ID`),
  ADD CONSTRAINT `TournamentMatch_ibfk_2` FOREIGN KEY (`ChallengedID`) REFERENCES `Fencer` (`ID`),
  ADD CONSTRAINT `TournamentMatch_ibfk_3` FOREIGN KEY (`Judge`) REFERENCES `Fencer` (`ID`),
  ADD CONSTRAINT `TournamentMatch_ibfk_4` FOREIGN KEY (`Referee1`) REFERENCES `Fencer` (`ID`),
  ADD CONSTRAINT `TournamentMatch_ibfk_5` FOREIGN KEY (`Referee2`) REFERENCES `Fencer` (`ID`),
  ADD CONSTRAINT `TournamentMatch_ibfk_6` FOREIGN KEY (`TournamentID`) REFERENCES `Tournament` (`ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;



--
-- Definition for udpDemoteFencerInTournament
--
DELIMITER $$
CREATE DEFINER=`66087_club_tour_admin`@`localhost` PROCEDURE `udpDemoteFencerInTournament`(IN `parTournamentID` INT, IN `parFencerID` INT, IN `parComment` TEXT)
    MODIFIES SQL DATA
    SQL SECURITY INVOKER
BEGIN
    DECLARE errno SMALLINT UNSIGNED DEFAULT 31001;
  DECLARE errmsg TEXT DEFAULT '';

    DECLARE varOldRank INT;
    DECLARE varNewRank INT;
    
  IF NOT EXISTS (SELECT 1 FROM Tournament WHERE ID = parTournamentID) THEN
      SET errmsg = 'TournamentID ' || parTournamentID || ' does not exist';
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = errno, MESSAGE_TEXT = errmsg;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM Fencer WHERE ID = parFencerID) THEN
        SET errmsg = 'FencerID ' || parFencerID || ' does not exist';
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = errno, MESSAGE_TEXT = errmsg;
    END IF;

    SELECT Rank INTO varOldRank FROM TournamentFencer WHERE FencerID = parFencerID AND TournamentID = parTournamentID;
    SELECT Rank + 1 INTO varNewRank FROM TournamentFencer WHERE FencerID = parFencerID AND TournamentID = parTournamentID;

    INSERT
      INTO RankChangeLog
         ( ChangeDateTime
         , TournamentID
         , FightID
         , FencerID
         , NewRank
         , OldRank
         , Comment
         )
    VALUES
         ( NOW()
         , parTournamentID
         , -1
         , parFencerID
         , varNewRank
         , varOldRank
         , parComment
         );

    UPDATE TournamentFencer
       SET Rank = varOldRank
     WHERE TournamentID = parTournamentID
       AND Rank = varNewRank;

    UPDATE TournamentFencer
       SET Rank = varNewRank
     WHERE TournamentID = parTournamentID
       AND FencerID = parFencerID;


END$$
DELIMITER ;



--
-- Definition for udpInitalizeRank
--
DELIMITER $$
CREATE DEFINER=`66087_club_tour_admin`@`localhost` PROCEDURE `udpInitalizeRank`()
    MODIFIES SQL DATA
BEGIN
UPDATE TournamentFencer tf
JOIN (
    SELECT ID,
           TournamentID,
           FencerID,
           ROW_NUMBER() OVER (PARTITION BY TournamentID ORDER BY RAND()) AS new_rank
    FROM TournamentFencer
    WHERE Rank IS NULL
) ranked
ON tf.TournamentID = ranked.TournamentID AND tf.FencerID = ranked.FencerID
SET tf.Rank = ranked.new_rank;
END$$
DELIMITER ;


--
-- Definition for udpInitalizeTournament
--
DELIMITER $$
CREATE DEFINER=`66087_club_tour_admin`@`localhost` PROCEDURE `udpInitalizeTournament`(IN `TournamentID` INT)
    MODIFIES SQL DATA
    SQL SECURITY INVOKER
BEGIN
    DECLARE errno SMALLINT UNSIGNED DEFAULT 31001;
  DECLARE errmsg TEXT DEFAULT '';
    
  IF NOT EXISTS (SELECT 1 FROM Tournament WHERE ID = TournamentID) THEN
      SET errmsg = 'TournamentID ' || TournamentID || ' does not exist';
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = errno, MESSAGE_TEXT = errmsg;
    END IF;

  INSERT
      INTO TournamentFencer (TournamentID, FencerID)
    SELECT TournamentID, ID
      FROM Fencer
     WHERE ID NOT IN (SELECT FencerID FROM TournamentFencer WHERE TournamentID = TournamentID);
END$$
DELIMITER ;


--
-- Definition for udpPromoteFencerInTournament
--
DELIMITER $$
CREATE DEFINER=`66087_club_tour_admin`@`localhost` PROCEDURE `udpPromoteFencerInTournament`(IN `parTournamentID` INT, IN `parFencerID` INT, IN `parComment` TEXT)
    MODIFIES SQL DATA
    SQL SECURITY INVOKER
BEGIN
    DECLARE errno SMALLINT UNSIGNED DEFAULT 31001;
  DECLARE errmsg TEXT DEFAULT '';

    DECLARE varOldRank INT;
    DECLARE varNewRank INT;
    
  IF NOT EXISTS (SELECT 1 FROM Tournament WHERE ID = parTournamentID) THEN
      SET errmsg = 'TournamentID ' || parTournamentID || ' does not exist';
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = errno, MESSAGE_TEXT = errmsg;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM Fencer WHERE ID = parFencerID) THEN
        SET errmsg = 'FencerID ' || parFencerID || ' does not exist';
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = errno, MESSAGE_TEXT = errmsg;
    END IF;

    SELECT Rank INTO varOldRank FROM TournamentFencer WHERE FencerID = parFencerID AND TournamentID = parTournamentID;
    SELECT Rank - 1 INTO varNewRank FROM TournamentFencer WHERE FencerID = parFencerID AND TournamentID = parTournamentID;

    INSERT
      INTO RankChangeLog
         ( ChangeDateTime
         , TournamentID
         , FightID
         , FencerID
         , NewRank
         , OldRank
         , Comment
         )
    VALUES
         ( NOW()
         , parTournamentID
         , -1
         , parFencerID
         , varNewRank
         , varOldRank
         , parComment
         );

    UPDATE TournamentFencer
       SET Rank = varOldRank
     WHERE TournamentID = parTournamentID
       AND Rank = varNewRank;


END$$
DELIMITER ;


--
-- Definition for udpRegisterMatchResults
--
DELIMITER $$
CREATE DEFINER=`66087_club_tour_admin`@`localhost` PROCEDURE `udpRegisterMatchResults`(IN `parTournamentID` INT, IN `parChallengerID` INT, IN `parChallengedID` INT, IN `parFightDate` DATE, IN `parChallengerScore` INT, IN `parChallangedScore` INT, IN `parChallengerWarnings` INT, IN `parChallengedWarnings` INT, IN `parDoubles` INT, IN `parJudge` INT, IN `parReferee1` INT, IN `parReferee2` INT, IN `parTable` INT)
    MODIFIES SQL DATA
BEGIN
    DECLARE errno SMALLINT UNSIGNED DEFAULT 31001;
    DECLARE errmsg TEXT DEFAULT '';
  
    DECLARE varChallengerInitalRank INT;
    DECLARE varChallangedInitalRank INT;
    
    DECLARE varFightID INT;
    
    DECLARE done INT DEFAULT FALSE;
    DECLARE varFencerID INT;
    DECLARE varInitalRank INT;
    DECLARE varNewRank INT;
    
    DECLARE cur_fencers CURSOR FOR
        SELECT FencerID, Rank
          FROM TournamentFencer
         WHERE TournamentID = parTournamentID
           AND Rank >= (
                            SELECT Rank
                              FROM TournamentFencer
                             WHERE TournamentID = parTournamentID
                               AND FencerID = parChallengedID
                             LIMIT 1
               )
           AND Rank < (
                            SELECT Rank
                              FROM TournamentFencer
                             WHERE TournamentID = parTournamentID
                               AND FencerID = parChallengerID
                             LIMIT 1
               )
      ORDER BY Rank;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
        
    -- Validate Inputs
    IF NOT EXISTS (SELECT 1 FROM Tournament WHERE ID = parTournamentID) THEN
        SET errmsg = CONCAT('Tournament ID ', parTournamentID, ' does not exist');
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = errno, MESSAGE_TEXT = errmsg;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM Fencer WHERE ID = parChallengerID) THEN
        SET errmsg = CONCAT('Fencer ID ', parChallengerID, ' does not exist');
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = errno, MESSAGE_TEXT = errmsg;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM Fencer WHERE ID = parChallengedID) THEN
        SET errmsg = CONCAT('Fencer ID ', parChallengedID, ' does not exist');
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = errno, MESSAGE_TEXT = errmsg;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM Fencer WHERE ID = parJudge) THEN
        SET errmsg = CONCAT('Fencer ID ', parJudge, ' does not exist');
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = errno, MESSAGE_TEXT = errmsg;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM Fencer WHERE ID = parReferee1) THEN
        SET errmsg = CONCAT('Fencer ID ', parReferee1, ' does not exist');
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = errno, MESSAGE_TEXT = errmsg;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM Fencer WHERE ID = parReferee2) THEN
        SET errmsg = CONCAT('Fencer ID ', parReferee2, ' does not exist');
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = errno, MESSAGE_TEXT = errmsg;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM Fencer WHERE ID = parTable) THEN
        SET errmsg = CONCAT('Fencer ID ', parTable, ' does not exist');
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = errno, MESSAGE_TEXT = errmsg;
    END IF;

     -- Get initial ranks
    SELECT Rank INTO varChallengerInitalRank FROM TournamentFencer WHERE FencerID = parChallengerID AND TournamentID = parTournamentID;
    SELECT Rank INTO varChallangedInitalRank FROM TournamentFencer WHERE FencerID = parChallengedID AND TournamentID = parTournamentID;

    -- Insert match
    INSERT
      INTO TournamentMatch
         ( TournamentID
         , ChallengerID
         , ChallengedID
         , FightDate
         , ChallengerScore
         , ChallangedScore
         , ChallengerWarnings
         , ChallengedWarnings
         , Doubles
         , ChallengerInitalRank
         , ChallangedInitalRank
         , Judge
         , Referee1
         , Referee2
         , MatchTable
         )
    VALUES
         ( parTournamentID
         , parChallengerID
         , parChallengedID
         , parFightDate
         , parChallengerScore
         , parChallangedScore
         , parChallengerWarnings
         , parChallengedWarnings
         , parDoubles
         , varChallengerInitalRank
         , varChallangedInitalRank
         , parJudge
         , parReferee1
         , parReferee2
         , parTable
         );
         
    SELECT LAST_INSERT_ID() INTO varFightID;

   -- Update ranks if Challenger won
    IF parChallengerScore > parChallangedScore THEN

        OPEN cur_fencers;
        read_loop: LOOP

            FETCH cur_fencers INTO varFencerID, varInitalRank;

            IF done THEN
                LEAVE read_loop;
            END IF;

            SET varNewRank = varInitalRank + 1;

            UPDATE TournamentFencer
               SET Rank = varNewRank
             WHERE TournamentID = parTournamentID
               AND FencerID = varFencerID;
               
            INSERT
              INTO RankChangeLog
                 ( ChangeDateTime
                 , TournamentID
                 , FightID
                 , FencerID
                 , NewRank
                 , OldRank
                 )
            VALUES
                 ( NOW()
                 , parTournamentID
                 , varFightID
                 , varFencerID
                 , varNewRank
                 , varInitalRank
                 );
        END LOOP read_loop;
    
        UPDATE TournamentFencer
           SET Rank = varChallangedInitalRank
         WHERE TournamentID = parTournamentID
           AND FencerID = parChallengerID;
          
        INSERT
          INTO RankChangeLog
             ( ChangeDateTime
             , TournamentID
             , FightID
             , FencerID
             , NewRank
             , OldRank
             )
        VALUES
             ( NOW()
             , parTournamentID
             , varFightID
             , parChallengerID
             , varChallangedInitalRank
             , varChallengerInitalRank
             );
    END IF;
    
END$$
DELIMITER ;