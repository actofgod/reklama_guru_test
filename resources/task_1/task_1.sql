
-- таблица пользователей
CREATE TABLE `users` (

  `id`    INTEGER     NOT NULL AUTO_INCREMENT,
  `login` VARCHAR(32) NOT NULL,

  CONSTRAINT `users_pk` PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARACTER SET = 'UTF8';

-- таблица категорий постов, при условии, что подкатегорий нет
CREATE TABLE `feed_news_categories` (

  `id`    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(32)       NOT NULL,

  -- если требуется древовидная структура категорий, и используется нативное представление дерева или таблица
  -- родительсих узлов дерева
  -- `parent_id`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  -- если требуется древовидная структура категорий, и используется nested set
--  `ns_key_left`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
--  `ns_key_right` SMALLINT UNSIGNED NOT NULL DEFAULT 1,

  CONSTRAINT `feed_news_categories_pk` PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARACTER SET = 'UTF8';

INSERT INTO `feed_news_categories` VALUES (1, 'root');

-- если требуется древовидная структура, и не используется nested set
-- ALTER TABLE `feed_news_categories` ADD CONSTRAINT `feed_news_categories_fk_parent_id`
--    FOREIGN KEY (`parent_id`) REFERENCES `feed_news_categories` (`id`)
--    ON UPDATE RESTRICT ON DELETE RESTRICT;
-- CREATE INDEX `feed_news_categories_idx_parent_id` ON `feed_news_categories` (`parent_id`);

-- если требуется древовидная структура, и не используется nested set
-- таблица родительсих узлов дерева
-- CREATE TABLE `feed_news_categories_ancestors` (
--   `ancestor_id` SMALLINT UNSIGNED NOT NULL,
--   `category_id` SMALLINT UNSIGNED NOT NULL,
--   `local_depth` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
--   CONSTRAINT `feed_news_categories_ancestors_fk_ancestor_id` FOREIGN KEY (`ancestor_id`)
--     REFERENCES `feed_news_categories` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
--   CONSTRAINT `feed_news_categories_ancestors_fk_category_id` FOREIGN KEY (`category_id`)
--     REFERENCES `feed_news_categories` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT
-- ) ENGINE = InnoDB DEFAULT CHARACTER SET = 'UTF8';

-- таблица постов
CREATE TABLE `feed_news_posts` (
  `id`          INTEGER UNSIGNED  NOT NULL AUTO_INCREMENT,
  `category_id` SMALLINT UNSIGNED NOT NULL,
  `likes`       INTEGER UNSIGNED  NOT NULL DEFAULT 0,
  `title`       VARCHAR(32)       NOT NULL,
  `content`     VARCHAR(245)      NOT NULL,
  `date`        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `feed_news_posts_pk` PRIMARY KEY (`id`),
  CONSTRAINT `feed_news_posts_fk_category_id` FOREIGN KEY (`category_id`)
    REFERENCES `feed_news_categories` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT

) ENGINE = InnoDB DEFAULT CHARACTER SET = 'UTF8';

-- покрывающий индекс по айди категории
CREATE INDEX `feed_news_posts` ON `feed_news_posts`(`category_id`);

-- таблица лайков, при наличии записи - лайк есть, при отсутствии - нет
CREATE TABLE `feed_news_likes` (
  `post_id` INTEGER UNSIGNED  NOT NULL,
  `user_id` INTEGER           NOT NULL,
  `date`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `feed_news_likes_pk` PRIMARY KEY (`post_id`, `user_id`),
  CONSTRAINT `feed_news_likes_fk_post_id` FOREIGN KEY (`post_id`)
    REFERENCES `feed_news_posts` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `feed_news_likes_fk_user_id` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARACTER SET = 'UTF8';

-- покрывающий индекс по айди пользователя
CREATE INDEX `feed_news_likes_idx_user_id` ON `feed_news_likes` (`user_id`);

-- индекс требуется для того, чтоб получать список лайков, отсортированных по дате лайка
CREATE INDEX `feed_news_likes_idx_date` ON `feed_news_likes` (`date`);

-- триггеры для обновления счётчиков лайков в таблице feed_news_posts
DELIMITER |

-- увеличивает количество лайков у поста при вставке нового лайка
CREATE TRIGGER `t_ai_feed_news_likes` AFTER INSERT ON `feed_news_likes` FOR EACH ROW
  UPDATE `feed_news_posts` SET `likes` = `likes` + 1 WHERE `id` = NEW.`post_id`;

-- уменьшает количество лайков у поста при удалении лайка из таблицы лайков
CREATE TRIGGER `t_ad_feed_news_likes` AFTER DELETE ON `feed_news_likes` FOR EACH ROW
  UPDATE `feed_news_posts` SET `likes` = `likes` - 1 WHERE `id` = OLD.`post_id`;

|

-- вставка нового поста
INSERT INTO `feed_news_posts` (`category_id`, `title`, `content`) VALUES
  (:categoryId, :postTitle, :postContent);

-- лайк пользователем поста
INSERT INTO `feed_news_likes` (`post_id`, `user_id`) VALUES
  (:postId, :userId);

-- снятие лайка пользователем
DELETE FROM `feed_news_likes` WHERE `post_id` = :postId AND `user_id` = :userId;

-- удаление поста
BEGIN WORK;
DELETE FROM `feed_news_likes` WHERE `post_id` = :postId;
DELETE FROM `feed_news_posts` WHERE `id` = :postId;
COMMIT;

-- получение списка постов для отображения, без фильтра по категориям
-- в фиде отображаем посты в обратном порядке
-- если требуется сортировка по дате и дата может меняться, то требуется накатить индекс на колонку даты и сортировать
-- по этой колонке
SELECT
  c.title,
  p.*
FROM
  `feed_news_posts` p
  INNER JOIN `feed_news_categories` c ON p.`category_id` = c.`id`
ORDER BY
  p.`id` DESC
LIMIT :offset, :limit;

-- получение списка постов для отображения, с фильтром по категориям
SELECT
  c.title,
  p.*
FROM
  `feed_news_posts` p
  INNER JOIN `feed_news_categories` c ON p.`category_id` = c.`id`
WHERE
  `category_id` = :categoryId
ORDER BY
  p.`id` DESC
LIMIT :offset, :limit;

-- если

-- получение списка пользователей, лайкнувших пост
-- сортируем в обратном порядке по дате лака
-- вообще скорее всего будет не "l.`post_id` = :postId", а "l.`post_id` IN (...)"
SELECT
  u.`id`,
  u.`login`,
  l.`date`
FROM
  `feed_news_likes` l
  INNER JOIN `users` u ON l.`user_id` = u.`id`
WHERE
  l.`post_id` = :postId
ORDER BY
  l.`date` DESC
LIMIT :offset, :limit;
