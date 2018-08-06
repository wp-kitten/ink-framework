## How to use \Ink\Security\Base

The Security module can be enabled after the framework has been setup (see setup.md).

It provides a variety of security settings that you can choose to enable or not from the Security page.

If enabled, the options will be available in the Settings > Ink Security

To change the menu title, use the **ink-framework/security/menu-title** filter.
To change the page title, use the **ink-framework/security/page-title** filter.

### Usage

Enable the Security module:
```php
/**
 * @param string $fwInstanceName The name of the framework instance (see setup.md)
 */
Ink\Security\Base::init( $fwInstanceName );
```

### Example
```php
Ink\Security\Base::init( 'tester' );
```
