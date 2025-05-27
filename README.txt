README
The Procrastinators
1. XAMPP must be installed to host the site locally.
2. Download the zip file for the project and extract it.
3. Place the website folder in htdocs folder of XAMPP.
4. Create the database using the database dump provided on platforms such as MyPhpAdmin which is what was used for this website.
5. Next setup how to make .env files work with php. 
5.1. Make sure you have git installed. Can be downloaded here: https://git-scm.com/downloads/win 
5.2. Make sure you have composer installed: https://getcomposer.org/download/ 
5.3. Add php to you system environment variable path. Should be something like this "C:\xampp\php". Add that to your path environment variable.
5.4. Open a terminal and navigate to the path of where the config.php is in the website (in htdocs from previous step). This is where you are going to create your .env file.
5.5. In the terminal at this location run this command: 
composer require vlucas/phpdotenv
5.6. The website will not work unless this is setup properly. Please ensure that the steps are followed and properly and the phpdotenv is installed correctly and fully.
6. Now it is needed to set up a .env file that contains the following database credentials, DB_HOST(where the database is hosted), DB_USER(database username), DB_PASS(database password) and DB_NAME(name of the database) This must be created in the same place as the config.php file.
7. A .gitignore file may be created and .env placed in it to avoid committing sensitive credentials.
8. Open XAMPP Control Panel  and start an Apache server.
9. In your preferred browser visit the following URL to access the website once XAMPP is running: http://localhost/website/
