## \Ink\Notices

Notices can be setup in 2 ways: **AdminNotice** & **UserNotice**.

The admin notices can only be seen by site administrators (users in role of administrator).
The user notices can only be seen by the specific user that was logged in at the moment the notice was generated.

Notices can be non-persistent and **dismissible** (persistent).

### Ink\Notices\Admin[User]Notice::add( $text, $type = self::TYPE_ERROR, $persistent = true )


### Persistent notices
```php
AdminNotice::add( '[Ink-Framework] ' . esc_html__( 'This is an informational notice.', 'ink-fw' ), AdminNotice::TYPE_INFO );
AdminNotice::add( '[Ink-Framework] ' . esc_html__( 'This is an error notice.', 'ink-fw' ), AdminNotice::TYPE_ERROR );
AdminNotice::add( '[Ink-Framework] ' . esc_html__( 'This is a warning notice.', 'ink-fw' ), AdminNotice::TYPE_WARNING );
AdminNotice::add( '[Ink-Framework] ' . esc_html__( 'This is a success notice.', 'ink-fw' ), AdminNotice::TYPE_SUCCESS );
```

### Non-Persistent notices

```php
AdminNotice::add( '[Ink-Framework] ' . esc_html__( 'This is an informational notice.', 'ink-fw' ), AdminNotice::TYPE_INFO, false );
AdminNotice::add( '[Ink-Framework] ' . esc_html__( 'This is an error notice.', 'ink-fw' ), AdminNotice::TYPE_ERROR, false );
AdminNotice::add( '[Ink-Framework] ' . esc_html__( 'This is a warning notice.', 'ink-fw' ), AdminNotice::TYPE_WARNING, false );
AdminNotice::add( '[Ink-Framework] ' . esc_html__( 'This is a success notice.', 'ink-fw' ), AdminNotice::TYPE_SUCCESS, false );
```

The same goes for **UserNotice**.
