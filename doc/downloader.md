## How to use \Ink\Helpers\Downloader

The Downloader class is a simple helper class that developers can use to download various files. These files will be downloaded to "wp-content/uploads/ink-framework" directory.

### Definition

```php
/**
 * Retrieve the path to the downloaded file
 * @param string $filePath
 * @param string $saveFileName Optional, the file name plus extension to save the file as
 * @param array $wpRequestArgs The list of arguments to override the default WP request settings
 * @see \WP_Http::request()
 * @return string The path to the downloaded file
 */
Ink\Helpers\Downloader::get( $filePath, $saveFileName = '', $wpRequestArgs = [] );


### Usage

```php
$download = Ink\Helpers\Downloader::get( 'http://example.com/path/to/archive/cities.zip', 'cities.zip' );

//#! On success, $download will be an array having the following structure
[
  'path' => '/sytem/path/to/wp-content/uploads/ink-framework/cities.zip',
  'url' => 'http://your-website.com/wp-content/uploads/ink-framework/cities.zip',
]

//#! On error, $download will be a boolean false

```
