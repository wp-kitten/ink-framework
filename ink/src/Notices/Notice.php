<?php

namespace Ink\Notices;

if ( ! defined( 'INK_FRAMEWORK' ) ) {
	exit();
}

/**
 * Class NoticeBase
 * @package Ink\Notices
 *
 * Base class for notices
 */
class Notice
{
	//#! Notice types
	const TYPE_INFO = 'info';
	const TYPE_ERROR = 'error';
	const TYPE_WARNING = 'warning';
	const TYPE_SUCCESS = 'success';
}
