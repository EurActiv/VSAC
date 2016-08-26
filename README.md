# Very Simple Asset Coordinator | VSAC

**NOTE:** This application is currently in Alpha status.

##About VSAC

VSAC is an online asset manager targeted at medium sized websites that need to manage assets from diverse sources.  It fills the following use cases:

  * **[SOA Mapping](./application/docs/callmap.md)**: VSAC provides you with an easy method to map your Service-oriented architecture.  It will automatically log calls between the nodes in your infrastructure and provide this information as an easy to navigate map.
  * **[Endpoint Documentation][1]**: VSAC provides an methods for you to document your styles, javascripts and API endpoints at the source.  It's an easy way communicate elements of your architecture to your team or the public.
  * **[Consolidated toolchain](./application/docs/server-config.md)**: VSAC provides built-in interfaces to compile and minify stylesheets, scripts and images without having to install and configure things like Compass or UglifyJS on every development workstation.
  * **[Versioned scripts and stylesheets](./application/docs/versioning.md)**: When a website is redesigned or updated, often old versions of the markup remain in archives or proxies. This application allows you to keep multiple versions of scripts and stylesheets online in parallel.
  * **API Shims**: Sometimes APIs need to talk to each other, but they don't speak the same language. Or, an API may need to query another API that can't handle the load.  This application provides facilities to stick a translation or caching shim between them. The [RSS to JSON endpoint](./application/plugins/feeds/feed.php) in the Feed Reader plugin is an example of such a shim.
  * **Microservices**: Full blown web services are outside of the scope of this project, but the framework is flexible enough to allow you to build small, fast microservices. The built-in [privacy protecting social share plugin](./application/plugins/social) is an example of such a micro service.

##Plugins

VSAC works based on a front controller that dispatches requests to plugins. The built-in plugins are:

  * **[Call Log](./application/plugins/call-log)** a service to log calls in the [API call map](./application/docs/callmap.md) without writing a whole plugin.
  * **[CDN](./application/plugins/cdn)** a CDN for distributing static Javascript and CSS assets. It will additionally minify the assets and ensure that all of the proper HTTP headers are set for maximum cachability.
  * **[Feeds](./application/plugins/feeds)**: Facilities for working with RSS feeds. It can convert them to JSON or inject the full content of the target item into the feed description for easier republishing.
  * **[Lazy Load](./application/plugins/lazy-load)**: An image lazyload script and API. The script handles lazy-loading images and the backend handles resizing, cropping and caching of the target images.
  * **[Logos](./application/plugins/logos)**: A simple file storage mechanism, for storing and serving static images, such as logos and favicons.
  * **[Social](./application/plugins/social)**: A micro service for generating share buttons with share counts. It tries to maximize user privacy by proxying share count requests to itself where possible rather than having the browser request these numbers directly.

##Installation

It's easy to install, see:

 * The [server configuration](./application/docs/server-config.md). VSAC is designed to run on Linux/Apache.
 * Using the [command line install script](./application/docs/installation.md) to install the application.
 * [Configuring the application](./application/docs/configuration.md) for your specific use case after installation.

##Extending the application

The application is easy to extend and supports writing your own plugins or overriding most of of the core application. For more information, see:

  * [Application overview](./application/docs/overview.md): an overview of how the application is structured and how to add/override elements in it.
  * [Configuration](./application/docs/configuration.md): a guide to configuring the application and plugins.
  * [Writing Plugins](./application/docs/plugins.md): a guide to writing plugins.
  * [Writing Modules](./application/docs/modules.md): a guide to writing modules (aka, libraries) or overriding existing ones.
  * [Writing CLI commands](./application/docs/cli.md): a guide to writing command line utilities.

For developent tools, examples and how-tos, see the [examples extension][2].

##License and contributions

This application is provided under the [MIT License][3]. We'd be very happy to get contributions, they must simply be made with the same license.

[1]: https://github.com/EurActiv/VSAC-Examples/blob/master/examples/plugins/example-plugin/example-controller-documentation.php
[2]: https://github.com/EurActiv/VSAC-Example
[3]: https://opensource.org/licenses/MIT


