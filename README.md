# How to setup a development environment with docker

## Procedure

- dump database from https://phpmyadmin.ovh.net/db_export.php?db=parlemonde-plm
	- Use "Custom - display all possible options"
	- Make sure to increase "Maximal length of created query" to a large value, e.g. 5000000
- start mysql docker container:
	docker run --restart unless-stopped --name mysql_plm -e MYSQL_DATABASE=plm -e MYSQL_ALLOW_EMPTY_PASSWORD=true -d mysql:5 --max-allowed-packet=128m --character-set-server=utf8 --collation-server=utf8_unicode_ci
- restore dump (use powershell on windows)
    cat C:\Users\Olivier\Downloads\parlemonde-plm.sql | docker exec -i mysql_plm /usr/bin/mysql -u root --password= plm
- update database to use local installation:
```mysql
update wp_options set option_value = 'http://localhost:28080' where option_name = 'home';
update wp_site set domain = 'localhost:28080';
update wp_sitemeta set meta_value = 'http://localhost:28080' where meta_key = 'siteurl';
update wp_blogs set domain = 'localhost:28080' where blog_id = 1;
```
- clone git repo
	git clone https://github.com/larnoult/parlemonde
- start wordpress
	docker run --rm --name wordpress_plm --volume C:\Users\Olivier\Documents\git\parlemonde:/var/www/html --link mysql_plm:mysql -p 28080:80 -e DOMAIN_CURRENT_SITE=localhost:28080 -e NOBLOGREDIRECT=http://localhost:28080 -d wordpress

	Note: command line instructions are given for powershell on Windows. If using cmd, add double quotes around paths.

## TODO

- disable caching in wp-config.php:
	define('WP_CACHE', false); //true);
- increase php memory limit
	define( 'WP_MEMORY_LIMIT', '512M' );
- use getenv() with default value for production
- chown -R www-data /var/www

- wp-config.php: configure DOMAIN_CURRENT_SITE and NOBLOGREDIRECT
- convert absolute URLs to relative URLs
- /home/parlemon/www WTF?
	- WPCACHEHOME in wp-config.php
	- in php files under wp-content\plugins\dk-pdf\tmp\ttfontdata
	
Sources:
- https://codeblog.dotsandbrackets.com/migrate-wordpress-docker/
- https://codepen.io/mttschltz/post/run-an-existing-wordpress-site-on-docker-locally-on-windows-10-home-64bit
- https://codex.wordpress.org/Moving_WordPress#On_Your_Existing_Server
- https://passion.digital/blog/migrating-wordpress-multisite-database-local-live/