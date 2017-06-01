
CREATE TABLE `users` (
  `id`     INTEGER       NOT NULL AUTO_INCREMENT,
  `name`   VARCHAR(32)   NOT NULL,
  `gender` TINYINT(2)    NOT NULL,
  `email`  VARCHAR(1024) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARACTER SET 'UTF8';

CREATE TABLE _tmp_email_domains_counters (
  `domain`  VARCHAR(256) NOT NULL,
  `counter` INTEGER      NOT NULL,
  PRIMARY KEY (`domain`)
) ENGINE = Memory DEFAULT CHARACTER SET 'UTF8';