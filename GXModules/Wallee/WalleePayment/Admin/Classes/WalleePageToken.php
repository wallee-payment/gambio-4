<?php declare(strict_types=1);

namespace GXModules\Wallee\WalleePayment\Admin\Classes;

/**
 * CSRF token for the admin order action endpoints.
 *
 * The shop's own page token is not used here: an invalid core token ends the request with
 * die('Unsecure page token!') and HTTP 200, a missing one with an uncaught exception and HTTP 500,
 * and the whole core token system can be switched off with ACTIVATE_PAGE_TOKEN, in which case every
 * submitted value is accepted. This token is always validated and lets the controller answer with 401.
 */
class WalleePageToken
{
	protected const SESSION_KEY = 'wallee_page_token';

	/**
	 * Returns the token of the current session and creates it on first use.
	 *
	 * @return string
	 */
	public static function get(): string
	{
		if (empty($_SESSION[self::SESSION_KEY]) || !\is_string($_SESSION[self::SESSION_KEY])) {
			$_SESSION[self::SESSION_KEY] = \bin2hex(\random_bytes(32));
		}

		return $_SESSION[self::SESSION_KEY];
	}

	/**
	 * @param mixed $token
	 * @return bool
	 */
	public static function isValid($token): bool
	{
		$sessionToken = $_SESSION[self::SESSION_KEY] ?? null;

		if (!\is_string($sessionToken) || $sessionToken === '' || !\is_string($token) || $token === '') {
			return false;
		}

		return \hash_equals($sessionToken, $token);
	}
}
