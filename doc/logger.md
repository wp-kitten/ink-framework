## How to use \Ink\Helpers\Logger

The logging class is a standard Singleton and it can be uniquely identified by the "unique-id" that is provided when the framework was setup.


### Usage

You can use the logger by calling the instance setup by the framework (see setup.md):
```php
$logger = \Ink\Framework::getInstance('tester')->getLogger();
```

or, by requesting the instance right from the Logger class:
```php
$logger = \Ink\Helpers\Logger::getInstance('tester');
```



### write( $text, $data, $loggingLevel )
```php
\Ink\Helpers\Logger::getInstance( 'unique-id' )
	->write( "This is an informative entry", [ 'details' => 'go here' ], \Ink\Helpers\Logger::INFO );
```
