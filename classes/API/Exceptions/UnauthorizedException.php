<?php
namespace StartupAPI\API\Exceptions;

/**
 * Thrown when user is authenticated, but not allowed to make a request
 *
 * @package StartupAPI
 * @subpackage API
 */
class UnauthorizedException extends APIException {

	function __construct($message = "Request forbidden") {
		parent::__construct($message, 403);
	}

}
