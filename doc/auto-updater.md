## Auto Updater (\Ink\AutoUpdate\Updater)

The Updater class provides utility methods that plugin or theme authors can use to automatically apply updates or patches to their products.

To work correctly, this class require the following configuration:
* a public json file on a remotely server

The public json file, **must** have the following structure:
```json
{
    "1.0" : {
        "version" : "1.0.1",
        "url": "http(s)://example.com/update-archive.zip"
    }
}
```
**1.0** designates the targeted version of your product that you want to update

**version** is the version of the patch or the full update

**url** is the publicly accessible path to the update archive



The **update-archive.zip** archive **must** have the following structure:
* directory (named exactly as the plugin's or theme's directory - otherwise it will not work as expected) 
	* (if patch) the modified files
	* (if full update) all the files from your plugin or theme


### Patches
When patches are provided as updates, make sure you add the following entry in the comment of your product: Patch: version. This will prevent applying the patch over and over again.
For example, if the product is a plugin (**test-plugin/index.php**) and its current version is **1.0** and you provide a patch (**v1.0.1**) for this version, then the json file must have the following structure:

```json
{
    "1.0" : {
        "version" : "1.0.1",
        "url": "http(s)://example.com/update-archive.zip"
    }
}
```

The structure of the **update-archive.zip** archive must be like this:
* test-plugin (the directory)
	* index.php (the plugin's main file)
	* other files or directories...


The comment of **index.php** file, must contain the **Patch** entry:
```php
/**
 * Plugin Name: Test Plugin
 * Plugin URI: http://example.com
 * Description: Test plugin
 * Version: 1.0
 * Patch: 1.0.1
 * Author: your-name
 * Text Domain: test-plugin
 */
```


### Updater::init($options = [])

The **$options** array has the following structure:
```php
    /*
     * Whether or not to automatically apply the update. If "false" then an admin notice will be displayed informing about the update so the administrators can select whether or not they want to apply the update. If "true", then the update will be applied automatically.
     * @optional
     */
    'auto-update' => false,
    /*
     * The interval, in hours, between updates check. Defaults to 12h. Any value less than 4 is considered invalid and the interval is reset to 12.
     * @optional
     */
    'update-check-interval' => 12,
    /*
     * The URL to the endpoint to get the response from. This is the path to the json file that stores the updates information.
     * @required
     */
    'endpoint' => '',
    /*
     * The system path to the plugin/theme file. If the product is a plugin, then the path is to the plugin's file, otherwise to the theme's stylesheet.
     * @required
     */
    'product-file-path' => '',
    /*
     * The name of the product to check for updates
     * @required
     */
    'product-name' => '',
    /*
     * The type of the product to check for updates: plugin or theme. Use either **Updater::TYPE_PLUGIN** or **Updater::TYPE_THEME**
     * @required
     */
    'product-type' => ''
```


### Setup the Updater

```php
\Ink\AutoUpdate\Updater::init([
	'auto-update' => 1,
	'endpoint' => 'http://example.com/updates/patches.json',
	'update-check-interval' => 12,
	'product-name' => 'Test plugin',
	//#! __FILE__ if the Updater is initialized in the plugin's main file
	'product-file-path' => __FILE__,
	'product-type' => \Ink\AutoUpdate\Updater::TYPE_PLUGIN,
]);
```

That's all, the Updater will take care of the rest of the process.





