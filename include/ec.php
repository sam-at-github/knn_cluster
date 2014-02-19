<?php
/**
 * So PHP has two error flagging mechanisms, the old triggered type, called errors herein, and newer Exceptions.
 * You want to use one or the other for clean and consistent coding. Legacy PHP code uses errors and you cant avoid that.
 * PHP provides ErrorExpection to allow you map errors to exceptions. This PHP file does that but there are some things to note:
 * 1. Only some user handle-able errors are mapped to exceptions - we make an assertion about which ones should and should not (see below).
 * 2. *But* uncaught exceptions are mapped back to errors so they can be logged and handle similarly to non Expection class errors (it does make sense).
 * 3. Uncaughts must be handled by the old style shutdown error handling in client code - uncaught exception become errors.
 * 4. We have to make assertions about the mapping between PHP errors and exceptions and other error conditions.
 *    That is, we map a subset of them to exceptions and leave the rest.
 * 
 * Which Errors to make Expections: 
 *  Let us define three broad category of condition: ERROR, EXCEPTION, NOTICE.
 *  Let ERROR class be unconditionally fatal.
 *  Let EXCEPTION class map to PHP Exceptions which are conditionally fatal.
 *  Let NOTICE class stay as PHP "error" types.
 *  The following error types are handle-able by the user as of ~ 5.2:
 *   E_NOTICE, E_USER_NOTICE, E_WARNING, E_USER_WARNING, E_RECOVERABLE_ERROR, E_USER_ERROR, E_USER_DEPRECATED, E_DEPRECATED
 *   "The following error types cannot be handled with a user defined function in 5.2: 
 *   E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING, 
 *   and most of E_STRICT raised in the file where set_error_handler() is called."
 *  Let the mapping for the handle-able error types (the rest are fatal and in ERROR class) be:
 *    ERROR: E_USER_ERROR
 *    EXCEPTION: E_WARNING E_USER_WARNING E_RECOVERABLE_ERROR
 *    NOTICE: E_NOTICE E_USER_NOTICE E_USER_DEPRECATED E_DEPRECATED <UNKNOWN>
 *  To account for possible unfortunate addition of error types and those parts of E_STRICT that arent 'most of';
 *  Note The handle-ableset of error types is unlikely to expand beyond E_USER_ERROR.
 *  And, we can err on side of caution and make all unknown types into Exceptions or not and make them Notices. Or not.
 *
 * Notes in Summary:
 *  1. Some Errors mapped to exceptions.
 *  2. All uncaught exceptions mapped (back) to errors of type E_USER_ERROR.
 *  3. NEVER set a default exception handler if using ec.php.
 *  4. The global $error_get_last holds the last error (or Exception) description for use in handling fatal errors.
 *  5. Error logging is handled via usual built-in fns except by def rerouted errors are not logged since you have opportunity to log the Exception.
 * 
 * Example error handler.
 *  function handle_errors() {
 *    global $error_get_last;
 *    if( isset( $error_get_last ) && ( $error_get_last['type'] &~ EC_CONTINUE ) )
 *      print "Error: ".$error_get_last['message']."\n";
 *    }
 * @see http://au2.php.net/manual/en/class.errorexception.php, http://au2.php.net/manual/en/errorfunc.configuration.php		
 */

//These should not change if PHP is a sane language, but its not. Wont account for version < 5.2.
if( version_compare( phpversion(), "5.3" ) >= 0 )
{
	define( "EC_CONTINUE", E_NOTICE | E_USER_NOTICE | E_STRICT | E_USER_DEPRECATED | E_DEPRECATED ); //<UNKNOWN>
}
else
{
	define( "EC_CONTINUE", E_NOTICE | E_USER_NOTICE | E_STRICT ); //<UNKNOWN>
}

define( "EC_RETHROW",  E_WARNING | E_USER_WARNING | E_RECOVERABLE_ERROR );
define( "EC_DIE", E_USER_ERROR );
define( "EC_FATAL", E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR );
//Define this if you want thrown errors logged too. But note they get logged when they are exceptions too.
//( ! defined( "EC_LOG_RETHROWN" ) ) && define( "EC_LOG_RETHROWN", true );


/**
 * Handle all user handle-able errors, and reroute Errors to Exceptions appropriately - see above.
 * Attemped reimplementation of inbuilt for non thrown. All error logging is handled according to normal methods.
 *	Note:
 *		"It is important to remember that the standard PHP error handler is completely bypassed." - PHP ~ 5.2 Manual.
 *		"If an user error handler successfully handles an error then that error will not be reported by this error_get_last()." - PHP ~ 5.2 Manual.
 * Unfortunately cant pass the errcontext into the ErrorException in any way. so that trace is lost, unless you set LOG_RETHROWN.
 */
function error_handler( $errno, $errmsg, $errfile, $errline, $errcontext )
{	
	//Log the error according to PHP_INI settings. ec_re_error_log() is a reimp of error_log().
	ec_re_error_log( $errno, $errmsg, $errfile, $errline );	
	
	//PHP will not set error_get_last() for overidden errors - "completely bypassed".
	//do this after ec_re_error_log() coz that casues E_STRICT.
	set_error_get_last( $errno, $errmsg, $errfile, $errline, $errcontext );

	//Reroute the condition as described above.
	if( EC_RETHROW & $errno )
	{
		 throw new ErrorException( $errmsg, $errno, $errno, $errfile, $errline ); //ErrorException::__construct ($message [, $code [, $severity [, $filename [, $lineno ]]]]]);
	}
	
	//Any shutdown_functions will be called as pre usual.
	elseif( EC_DIE & $errno )
	{
		exit(1);
	}
	
	else
	{
		return true;
	}	
}


/**
 * Reimplementation ~same as built-in error handler bar stack trace which you dont really get with Exceptions.
 * This is an attempted reimplementation of built-in handling.
 * Event is logged regardless of whether we decide to throw if EC_LOG_RETHROWN defined.
 * Most of the work is done by error_log().
 * ini_get always returns string, ALL booleans map to (("0"|"")|"1"). 
 * @returns bool true iff logged error. 
 */
function ec_re_error_log( $errno, $errmsg, $errfile, $errline )
{
	$logged = false; //whether a log was made.
	if( $errno & error_reporting() )
	{
		// Yes, 'log_errors' and 'display_errors' are independent.
		// "$errmsg is sent to PHP's system logger, using the Operating System's system logging mechanism or a file, 
		//	depending on what the error_log  configuration directive is set to. This is the default option."
		// 'display_errors' is pretty much just a switch - put *any* reportable errors on the output device.
		if( ini_get( "log_errors" ) )
		{
			//adds some stuff to beginning/end of $errmsg.
			//option for not logging errors that will be thrown.
			if( ( $errno & ~EC_RETHROW ) || defined( "EC_LOG_RETHROWN" ) )
			{
					error_log( make_error_log( $errno, $errmsg, $errfile, $errline ) );
					$logged = true;
			}
		}
		
		//"Value 'stderr' sends the errors to stderr instead of stdout. 
		//The value is available as of PHP 5.2.4. In earlier versions, this directive was of type boolean."
		if( ini_get( "display_errors" ) )
		{
		 	if( ini_get( "display_errors" ) == "stderr" )
			{
				$f_out = fopen( "php://stderr", "w" );
				fwrite( $f_out, ini_get( "error_prepend_string" ).make_error_log( $errno, $errmsg, $errfile, $errline ).ini_get( "error_append_string" ) );
			}
			else
			{
				print ini_get( "error_prepend_string" ).make_error_log( $errno, $errmsg, $errfile, $errline ).ini_get( "error_append_string" );
			}
		}		
	}
	return $logged;
}


/**
 * Reimplement built-in handlers output format.
 * as of PHP 5.3.
 */
function make_error_log( $errno, $errmsg, $errfile, $errline )
{ 
	//Some of these errors can not occur here. Copied from PHP manual and added the 2 XXX_DEPRECATED types.
	$errnotices = array (
      E_ERROR              => 'Fatal Error',	//nh,nr //modified string from 'Error'.
      E_WARNING            => 'Warning',	
      E_PARSE              => 'Parsing Error',	//nh,nr
      E_NOTICE             => 'Notice',
      E_CORE_ERROR         => 'Core Error',	//nh,nr
      E_CORE_WARNING       => 'Core Warning',	//nh
      E_COMPILE_ERROR      => 'Compile Error',	//nh,nr
      E_COMPILE_WARNING    => 'Compile Warning', 
      E_USER_ERROR         => 'User Error',	//#nr
      E_USER_WARNING       => 'User Warning',
      E_USER_NOTICE        => 'User Notice',
      E_STRICT             => 'Runtime Notice',	//#nh
      E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
      //E_DEPRECATED         => 'Deprecated Notice',
      //E_USER_DEPRECATED    => 'User Deprecated Notice', 
      );
	$errprep = ( ( array_key_exists( $errno, $errnotices ) ) ? $errnotices[$errno] : 'Unknown Error' );
	return "PHP ".$errprep.": ".$errmsg." in $errfile on line $errline";
}


/**
 * global $error_get_last is used in shutdown error handling.
 * @param errcontext maybe an Array or an Exception depending on what the last error was.
 */
function set_error_get_last( $errno, $errmsg, $errfile, $errline, $errcontext, $was_exception = false )
{
	global $error_get_last;
	$error_get_last = 
	array
	( 
		'type' => $errno,
		'message' => $errmsg, 
		'file' => $errfile,
		'line' => $errline,
		'context' => $errcontext,
		'was_exception' => $was_exception
	);
}	


/**
 * Handle all uncaught (terminal) exceptions, incorporating them into the rest of the error system by simply piping into an ~equivalent $error_get_last.
 * Basically the entire point of this is to undo PHP's $error_get_last['message'] setting from $e->__toString to $e->message.
 * Using this system you should NEVER set your own global Exception handler.
 * Note An uncaught exception is treated by PHP as a fatal error so we do the same here.
 * @param e Exception.
 */
function exception_handler( Exception $e )
{ 
	//Add bits to message to make it same as an exception and log it.
	ec_re_error_log( E_ERROR, "Uncaught ".$e->__toString()."\nthrown", $e->getFile(), $e->getLine() );
	//We need to tell shutdown functions an error occured via $error_get_last.
	//But error_get_last() not set if handler is set so.
	set_error_get_last( E_ERROR, $e->getMessage(), $e->getFile(), $e->getLine(), $e, true );
}


/**
 * Reroute errors to global we are using.
 * "each will be called in the same order as they were registered" - PHP ~ 5.2 Manual. T.f. this *will* be 1st called. 
 */
function ec_error_shutdown_handler()
{
	global $error_get_last;
	$unhandlable = error_get_last();
	if( $unhandlable && ( $unhandlable['type'] & EC_FATAL ) )
	{
		$error_get_last = $unhandlable;
	}
}

register_shutdown_function( "ec_error_shutdown_handler" );
//Set the above error handlers. We will handle *all* handle-able conditions.
set_error_handler( "error_handler" );
set_exception_handler( "exception_handler" );

?>
