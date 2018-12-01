# How to setup a development environment with docker

## Prerequisites
- Linux, MacOS or Windows with enough disk and RAM resources, say 100GB disk free and 4GB RAM.
- Docker CE. Check out [the installation instructions for your platform](https://docs.docker.com/install/).
## Procedure
- Dump database from https://phpmyadmin.ovh.net/db_export.php?db=parlemonde-plm
	- Use "Custom - display all possible options"
	- Make sure to increase "Maximal length of created query" to a large value, e.g. 5000000
- Start mysql docker container:
```powershell
docker run --restart unless-stopped --name mysql_plm -e MYSQL_DATABASE=plm -e MYSQL_ALLOW_EMPTY_PASSWORD=true -d mysql:5 --max-allowed-packet=128m --character-set-server=utf8 --collation-server=utf8_unicode_ci
```
- Restore dump:
```powershell
cat parlemonde-plm.sql | docker exec -i mysql_plm /usr/bin/mysql -u root --password= plm
```
Note: on Windows, you have to use PowerShell. For some reason, the pipe does not work with `cmd`.
- Update database to use local installation:
```mysql
update wp_options set option_value = 'http://localhost:28080' where option_name = 'home';
update wp_site set domain = 'localhost:28080';
update wp_sitemeta set meta_value = 'http://localhost:28080' where meta_key = 'siteurl';
update wp_blogs set domain = 'localhost:28080' where blog_id = 1;
```
As long as the `mysql_plm` container is not destroyed (it can be stopped though), the database is available.
- Clone git repo:
```powershell
git clone https://github.com/larnoult/parlemonde
```
- Start wordpress:
```powershell
docker run --rm --name wordpress_plm --volume C:\path\to\git\parlemonde:/var/www/html --link mysql_plm:mysql -p 28080:80 -e DOMAIN_CURRENT_SITE=localhost:28080 -e NOBLOGREDIRECT=http://localhost:28080 -d wordpress
```
Note: this command line is an example for PowerShell on Windows. If you are using `cmd`, enclose the path around double quotes.
- Connect your browser at http://localhost:28080.

## Changes
- To get the site loading, I had to disable caching in `wp-config.php` but this is highly inefficient:
```php
define('WP_CACHE', false); //true);
```
- Increase php memory limit:
```php
define( 'WP_MEMORY_LIMIT', '512M' );
```
- Set proper ownership of files inside the container:
```bash
chown -R www-data /var/www
```

## TODO
- Find a way to enable caching.
- Use `getenv()` with default value to a have configuration files working for both development and production.
- Convert absolute URLs to relative URLs.
- How to map all sites from the multisite configuration to a local address when in development?
- Troubleshoot issue with accented characters displayed as ??.

## Sources
- https://codeblog.dotsandbrackets.com/migrate-wordpress-docker/
- https://codepen.io/mttschltz/post/run-an-existing-wordpress-site-on-docker-locally-on-windows-10-home-64bit
- https://codex.wordpress.org/Moving_WordPress#On_Your_Existing_Server
- https://passion.digital/blog/migrating-wordpress-multisite-database-local-live/
