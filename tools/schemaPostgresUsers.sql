CREATE TABLE IF NOT EXISTS users (
 id varchar(20) NOT NULL, --user's id
 screen_name varchar(30) NOT NULL, --user's screen name (without @)
-- last_update varchar(15) NOT NULL, --timestamp of the last update
 last_update int8 NOT NULL,
 followers_count int NOT NULL, --number of followers at this time
 friends_count int NOT NULL --number of friends at this time
);
