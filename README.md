# CorExplorer: Web Portal for Gene Expression Analysis using CorEx

The portal hosts gene expression datasets which have been analyzed into 3-level factor sets using
<a href="https://github.com/gregversteeg/CorEx">CorEx</a>. The portal also performs per-factor
survival analysis plus GO/Kegg annotation, and protein-protein interaction (the latter two
use data from StringDB).

Example Installation (Cancer Genomics): http://corex.isi.edu

## Setting up a local installation

System requirements: Linux, 16G RAM, Apache, PHP, MySQL, R, git.
These instructions are for MySQL, however MariaDB should be similar. 

1. Install R libraries STRINGdb, survival, biomaRt; e.g. using BioConductor:
```
R>BiocManager::install("biomaRt")
R>BiocManager::install("STRINGdb")
R>BiocManager::install("survival")
```

2. Enable PHP to be served in .html files:

IF USING STANDARD PHP add to Apache configuration file:
```
RemoveHandler .html .htm
AddType application/x-httpd-php .php .htm .html
```

IF USING PHP-FPM add to the www.conf file:
```
<FilesMatch \.(html)$>
    SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"
</FilesMatch>
```

3. Make sure Rscript is at /usr/bin/Rscript; if not, make link. Likewise ensure php is at /usr/bin/php.

4. Dowload CorExplorer code: 
```
git clone https://github.com/gregversteeg/CorExplorer.git
```

5. Create directories for backend scripts and datasets, e.g. /var/corex_scripts, /var/corex_datasets

6. Copy backend scripts from git repo: 
```
sudo cp -R CorExplorer/backend/* /var/corex_scripts
```

7. Both directories need access by the web server. Find the web server group in the conf file, e.g. www-data. Then:

```
sudo chgrp -R www-data corex_scriptdir
sudo chgrp -R www-data corex_datadir
sudo chmod -R 2775 corex_datadir
```
    
8. Create corex database and load schema:
```
create database corex
use corex
source CorExplorer/backend/schema.sql
```

9. (Recommended) Create MySQL user corex and grant access to corex DB

10. Create CorExplorer admin user:  
```
cd CorExplorer/backend/aux
vi adduser.php : edit strings at top
php adduser.php
```

11. Set Apache server variables, in Apache config:
```
SetEnv DBUSER <corex mysql user>
SetEnv DBPASS <password>
SetEnv COREXDATADIR /var/corex_datadir
SetEnv COREXSCRIPTDIR /var/corex_scriptdir
sudo service apache2 restart
```

12. If you installed the R libraries as a local user, you need to add the R library path to the Apache environment, in Apache envvars file:
```
sudo vi /etc/apache2/envars
add line (example path): export R_LIBS_USER=/home/ubuntu/R/x86_64-pc-linux-gnu-library/3.5
sudo service apache2 restart
````

13. Copy CorExplorer web files from git repo to web root (e.g. /var/www/html)"
```
cd /var/www/html
sudo cp -R ~/CorExplorer/html/* .
```

14. Using a browser, open the diagnostic web page check_system.html, and fix any reported problems
