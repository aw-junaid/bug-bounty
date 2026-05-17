-- VULN: Weak root password
-- VULN: Grants all privileges without restriction

-- Set weak root password
ALTER USER 'root'@'%' IDENTIFIED BY 'root';

-- VULN: Create user with excessive privileges
CREATE USER IF NOT EXISTS 'slackuser'@'%' IDENTIFIED BY 'slackpass';
-- VULN: GRANT ALL instead of least privilege
GRANT ALL PRIVILEGES ON slack_clone.* TO 'slackuser'@'%';
FLUSH PRIVILEGES;

-- Import main schema
SOURCE /docker-entrypoint-initdb.d/schema.sql;

-- VULN: Enable general query log (logs all queries including data)
SET GLOBAL general_log = 'ON';
SET GLOBAL general_log_file = '/var/lib/mysql/mysql-general.log';

-- VULN: Enable slow query log with very low threshold
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0;
SET GLOBAL slow_query_log_file = '/var/lib/mysql/mysql-slow.log';
