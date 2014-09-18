CREATE TABLE IF NOT EXISTS gtweets (
  flag 	varchar(20) NOT NULL,
  text 	varchar(600)  NOT NULL,
  to_user_id varchar(100) NOT NULL,
  from_user varchar(100) NOT NULL,
  id varchar(100) NOT NULL,
  from_user_id varchar(100) NOT NULL,
  iso_language_code varchar(10) NOT NULL,
  source varchar(250) NOT NULL,
  profile_image_url varchar(1200) NOT NULL,
  geo_type varchar(30) NOT NULL,
  geo_coordinates_0 float8 NOT NULL,
  geo_coordinates_1 float8 NOT NULL,
  created_at varchar(50) NOT NULL,
  time int8 NOT NULL,
  from_user_name varchar(30),
  from_user_location varchar(40),
  from_user_url varchar(512),
  from_user_description varchar(320),
  from_user_created_at varchar(50),
  from_user_verified boolean,
  from_user_contributors_enabled boolean,
  truncated boolean,
  in_reply_to_status_id varchar(30),
  contributors varchar(200),
  initial_tweet_id varchar(100),
  initial_tweet_text varchar(600),
  initial_tweet_user varchar(60),
  initial_tweet_time int8,
  user_mentions varchar(1000),
  urls varchar(1200),
  medias_urls varchar(1000),
  hashtags varchar(200),
  symbols varchar(200),
  query_source varchar(50),
  vm_source varchar(100)
) ;


 create index idx_query_source on gtweets(query_source);
 create index idx_vm_source on gtweets(vm_source);
 create index idx_id on gtweets(id);

