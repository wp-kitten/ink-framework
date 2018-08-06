## Envato Product Updater (\Ink\Envato\ProductUpdater)

This class extends the Envato's Envato_Protected_API class to allow buyers to update plugins (from Code Canyon), feature which is not available by default. 

This allows plugin and theme developers to provide an easier way for their users to update their products using the WordPress updates screen.

This class is a revised version of: https://github.com/wp-kitten/envato-update-plugins

Based on: https://github.com/envato/envato-wordpress-toolkit

If enabled, the options will be available in the Settings > Ink Product Updater

To change the menu title, use the **ink-framework/product-updater/menu-title** filter.
To change the page title, use the **ink-framework/product-updater/page-title** filter.

### Usage

Add the following code snippet in your plugin's or theme's main file:
```php
$envatoUserInfo = \Ink\Envato\ProductUpdater::getUserCredentials();
$envatoUserName = $envatoUserInfo['user_name'];
$envatoApiKey = $envatoUserInfo['api_key'];
$envatoProductUpdater = new \Ink\Envato\ProductUpdater( $envatoUserName, $envatoApiKey, 'tester' );
add_action( 'admin_init', [ $envatoProductUpdater, 'onAdminInit' ] );
```

**tester** is the name of the Ink Framework instance given when instantiated. See **setup.md** for more info.


That's all. Now the **\Ink\Envato\ProductUpdater** class will retrieve and display the available updates for your products (if the user has them installed on their website) in the WordPress updates screen.
