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

14. Using a browser, open the diagnostic web page check_system.html, and fix any reported problems. 

15. Load auxiliary data from StringDB (Ensembl protein info, GOs, protein links):
```
cd CorExplorer/backend/aux
mkdir stringdb_files
cd stringdb_files
(note version numbers below will need to be updated!!)
wget https://stringdb-static.org/download/protein.links.v11.0/9606.protein.links.v11.0.txt.gz
wget https://string-db.org/mapping_files/geneontology/human.GO_2_string.2018.tsv.gz
wget https://stringdb-static.org/download/protein.info.v11.0/9606.protein.info.v11.0.txt.gz
gunzip *.gz
cd ..
edit file paths and then run the following scripts to load:
ppi_load.php
load_prot_names.php (takes about 30 mins)
load_ensp_go_mapping.php (takes about 30 mins)
```

16. The site should now be working. Login as admin user (created in step 10) and upload a test dataset as follows:
    * Browse to the top-level page. Login link is at lower right corner. 
    * After logging in, the login link changes to a link with your username; click that.
    * Now you are at your Manage Projects page. The first section is New Project. 
    * Enter a test project name
    * For data link use https://www.dropbox.com/s/fzfnkw930hugxrl/corex_test2.zip?dl=0
    * Press "Submit"; the page will reload
    * Now the "Current projects" table has your new project
    * Click "view log" at the right side of the table to track the loading progress
    * Loading should finish in about 30 mins and the status will change to READY
    
## Loading your own projects

You need a CorEx run output as well as several auxiliary files, as follows:
    * metadata.tsv : sample, DTD, DTLC, Status
    * Reduced_data.csv : (samples x genes) matrix of expression data
    * run_details.txt: general info about the project, for display in "dataset overview" link
    
To see the directory structure and details of these files, look at the example
https://www.dropbox.com/s/fzfnkw930hugxrl/corex_test2.zip?dl=0
