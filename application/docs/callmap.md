#Versioned assets

**See Also:** [Examples Extension](https://github.com/EurActiv/VSAC-Examples)

The application will generate call maps to help you map your service oriented
architecture.  By default, it is configured to store mapping for the core
plugins.  You can change this behavior by overriding the `callmap_driver`
setting in the plugin configurations frome `sqlitecallmap` to `noopcallmap`.

A callmap the architecture overview looks something like this:

![An overview call map](./callmap.png)

You can drill down to get more detail on individual elements in the callmap. A
map that looks at what a single client application is doing looks something
like this:

![An overview call map](./callmap-client.png)

To make a client application callmap aware, call the function `callmap_log`,
which has the following signature:

    /**
     * Log a call in the callmap log
     *
     * @param string $provider the provider (aka, the application answering the call)
     * @param string $consumer the consumer (aka, the application making the call),
     * will be extracted from Referer header if not set.
     * @param string $gateway the gateway in this application, usually the current
     * plugin or the current plugin + controller
     *
     * @return void
     */
    function callmap_log($provider, $consumer = null, $gateway = null)

To add applications to the callmap without writing an entire plugin, you can
use the `call-log` core module.


