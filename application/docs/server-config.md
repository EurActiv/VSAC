#Server Configuration

**See Also:** [Installation](./installation.md) | [Application Configuration](./configuration.md)

Your webserver needs to have:

  * Linux: The application won't run on Windows. Note: we've only tested this on Debian7, but there's no reason it should not work in other *nix'es.  Please submit a bug if you have an issue on other systems.
  * PHP >= 5.6: it also seems to work on 5.5 and 5.4.
  * Apache >= 2.2: PHP must be running as an Apache module
  * Imagick, as a PHP extension
  * The PHP cURL extension
  * The PHP Tidy extension
  * SQLite3 and the SQLite PDO driver (optional, but necessary for everything to work)
  * [UglifyJS2][2] >= 2.4
  * [Compass][3] >= 1.0

###Installing on Debian 7 (Wheezy)

**Repositories**

 * Add the official backports repository ( [instructions](https://backports.debian.org/Instructions/) )
 * Add the DotDeb repositories, both the standard repository and the "PHP 5.6 on Debian 7 'Wheezy'" repository ( [instructions](https://www.dotdeb.org/instructions/) )

**Packages**

This should install everything:

    root@machine:~/# apt-get install apache2 apache2-mpm-itk apache2-utils \
     php5 php5-cli php5-curl php5-imagick php5-intl php5-readline php5-sqlite \
     php5-tidy libapache2-mod-php5 sqlite3 nodejs ruby-full rubygems1.8
    root@machine:~/# npm install uglifyjs
    root@machine:~/# gem install sass
    root@machine:~/# gem install compass
    root@machine:~/# a2enmod rewrite

Notes:

 * You can substitute apache2-mpm-itk with apache2-mpm-prefork depending on how you like to set up your sever environment. VSAC probably works with FastCGI with the apache2-mpm-worker, but we haven't tested that configuration.
 * There are packages available for both uglifyjs and compass, but they're too old. Use nmp and gem to install them.

###Installing on Debian 8 (Jessie)

####With PHP7 (recommended)

**Repositories**

 * Add the DotDeb "Jessie" repository ( [instructions](https://www.dotdeb.org/instructions/) )

**Packages**

This should install everything:

    root@machine:~/# apt-get install apache2 apache2-mpm-itk apache2-utils \
     php7.0 php7.0-cli php7.0-curl php7.0-imagick php7.0-intl php7.0-readline \
     php7.0-sqlite php7.0-mbstring php7.0-tidy libapache2-mod-php7.0 sqlite3 \
     uglifyjs ruby-compass

_Note:_ At the time of writing, the version of UglifyJS packaged with Debian8 has a bug that breaks `uglifyjs --version`. As such, the `build` module will report that the correct version is not installed. That's OK, it works. If you want to squelch the error, you can install `npm` and use `npm install uglify-js` instead.


####With PHP5.6

No extra repositories are required. This should install everything:

    root@machine:~/# apt-get install apache2 apache2-mpm-itk apache2-utils \
     php5 php5-cli php5-curl php5-imagick php5-intl php5-readline php5-sqlite \
     php5-tidy libapache2-mod-php5 sqlite3 uglifyjs ruby-compass

_Note:_ The same UglifyJS bug as noted in the PHP7 section applies here.

###Installing on Ubuntu 16.04

This section is not complete

###Installing on CentOS 6

This section is not complete

###Installing on CentOS 7

This section is not complete
