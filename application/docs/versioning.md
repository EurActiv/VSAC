#Versioned assets

**See Also:** [Examples Extension](https://github.com/EurActiv/VSAC-Examples)

One of the big use-cases for this application is serving versioned assets.

In the course of an online application's lifetime, the assets that it requires may change dramatically. However, in the case of applications that have substantial numbers of more or less static web pages (especially CMSs), the pages being served may not be updated. For example, they may live for a long time in reverse proxies, online archives or the CMS may store old pages as static HTML. If a new asset resides at the same URL as the old asset or the old asset simply does not exist any more, the page will not render correctly.

The versioned assets in this application try to solve that issue by serving versioned assets from dedicated sub directories. The application itself provides several functions to assist in managing these assets.

In a versioning enabled plugin, versioned assets are stored in a subdirectory labeled `v1`, `v2`, ..., `v{$n}`. The directory `v-edge` is where development can happen. There are functions to publish the current "-edge" directory as a new version or to revert a published directory back to `-edge` to do backwards compatible maintenence.

###Development workflow

When doing normal application development with a versioned plugin, follow this
workflow:

  1. Create new assets (scripts, js, sass, images) in the plugin's `v-edge` directory.
  *  Develop the new files in parallel with the development you are doing in the consumer application (eg, updating the theme in your CMS).
  * Publish the `-edge` version to a new numbered version
  * Deploy the changes to your consumer application and update its references to the new "v{$n}" assets SIMULTAINEOUSLY.

###Maintenence workflow

When making changes that will not require changes in the consumer application (eg, backwards-compatible changes, bugfixes...), use the following workflow:

  1. Navigate to the version you wish to change.
  *  Use the version management box to revert the version to `-edge`
  *  Make your modifications on the version now located in the `v-edge` directory
  *  Use the version management box to re-publish the `-edge` version to the version you are modifying.


###Changelogs and PHAR archives

Versioned plugins should generally not be distributed as PHAR archives because they need to be able to modify themselves to work normally.  The application will _not_ store the changes in the source PHAR archive, it will store them in the extracted PHAR proxy, so your work will be lost whenever it updates the extracted proxy.  Besides, it's difficult to edit files within PHAR archives. In short, distribute versioned plugins as source.

Versioned plugins are _not_ meant to replace version control tools such as git.  There are many ways to store versioned assets in source control; we recommend creating a repository for the whole plugin.

The best way to get started is to take a look at the examples in the [example extension](https://github.com/EurActiv/VSAC-Examples).


