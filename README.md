# Very Simple Asset Coordinator | VSAC

**NOTE:** This application is currently in Alpha status.

##About VSAC

VSAC is an online asset manager targeted at medium sized websites that need to manage assets from diverse sources.  It fills the following use cases:

  * **SOA Documentation**: VSAC provides an easy way for you to document your styles, javascripts and API endpoints at the source.  It makes it easier to communicate elements of your architecture to your team or the public.
  * **Consolidated toolchain**: VSAC provides built-in interfaces to compile and minify stylesheets, scripts and images without having to install and configure things like SASS or UglifyJS on every development workstation.
  * **Versioned scripts and stylesheets**: When a website is redesigned or updated, often old versions of the markup remain in archives or proxies. This application allows you to keep multiple versions of scripts and stylesheets online in parallel.
  * **API Shims**: Sometimes APIs need to talk to each other, but they don't speak the same language. Or, an API may need to query another API that can't handle the load.  This application provides facilities to stick a translation or caching shim between them.
  * **Microservices**: Full blown web services are outside of the scope of this project, but the framework is flexible enough to allow you to build small, fast microservices.

##Plugins

VSAC works based on a front controller that dispatches requests to plugins. The built-in plugins are:

  * **CDN** a CDN for distributing static Javascript and CSS assets. It will additionally minify the assets and ensure that all of the proper HTTP headers are set for maximum cachability.
  * **Feeds**: Facilities for working with RSS feeds. It can convert them to JSON or inject the full content of the target item into the feed description for easier republishing.
  * **Lazy Load**: An image lazyload script and API. The script handles lazy-loading images and the backend handles resizing, cropping and caching of the target images.
  * **Logos**: A simple file storage mechanism, for storing and serving static images, such as logos and favicons.
  * **Social**: A script for generating share buttons with share counts. It tries to maximize user privacy by proxying share count requests to itself where possible rather than having the browser request these numbers directly.

##Installation

Instalation is pretty easy:

  1. Download the `application.phar` file from this repository and upload it to your webserver, outside of the document root.
  * In a console, run `$ php /path/to/application.phar`.
  * Select the option `install`.
  * Answer the questions it asks about where to install the application.
  * The installer will create an install called install.php in your current working directory. Review it (eg, `$ vi "$(pwd)/install.php"`) and then run it with PHP (eg, `$ php "$(pwd)/install.php"`).

##Configuration

Configuration is also easy. During the install process above, add a vendor directory (outside of the web root).  Select yes to the questions `Create directory "config"?` and yes to the question `Copy default configs?`. This will create a folder `/path/to/your/directory/config` that will contain the default configuration files for the application and the default plugins. Edit them as you see fit.


##Extending the application

The application is easy to extend and supports writing your own plugins or overriding most of of the core application. For more information, see:

  * [Application overview][1]: an overview of how the application is structured and how to add/override elements in it.
  * [Writing Plugins][2]: a guide to writing plugins.
  * [Writing Modules][3]: a guide to writing modules (aka, libraries) or overriding existing ones.
  * [Writing CLI commands][4]: a guide to writing command line utilities.


##License and contributions

This application is provided under the [MIT License][5]. We'd be very happy to get contributions, they must simply be made with the same license.


[1]: docs/overview.md
[2]: docs/plugins.md
[3]: docs/modules.md
[4]: docs/cli.md
[5]: https://opensource.org/licenses/MIT