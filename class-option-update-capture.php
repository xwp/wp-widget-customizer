<?php
/**
 * Capture updates to options so they don't get saved to the database
 * @todo add_option is not supported
 */

class Option_Update_Capture {

	/**
	 * @var array $options values updated while capturing is happening
	 */
	public $options = array();

	protected $_ignore_transients = true;
	protected $_is_current = false;

	function __construct( $ignore_transients = true ) {
		$this->_ignore_transients = $ignore_transients;
	}

	/**
	 * Determine whether or not the transaction is open
	 * @return bool
	 */
	function is_current() {
		return $this->_is_current;
	}

	/**
	 * @param $option_name
	 * @return boolean
	 */
	function is_option_ignored( $option_name ) {
		return ( $this->_ignore_transients && 0 === strpos( $option_name, '_transient_' ) );
	}

	/**
	 * Get the number of options updated
	 * @return bool
	 */
	function count() {
		return count( $this->options );
	}

	/**
	 * Start keeping track of changes to options, and cache their new values
	 */
	function start() {
		if ( $this->_is_current ) {
			return;
		}
		$this->_is_current = true;
		add_filter( 'pre_update_option', array( $this, 'pre_update_option' ), 10, 3 );
	}

	/**
	 * @param mixed $new_value
	 * @param string $option_name
	 * @param mixed $old_value
	 * @return mixed
	 * @filter pre_update_option
	 */
	function pre_update_option( $new_value, $option_name, $old_value ) {
		if ( $this->is_option_ignored( $option_name ) ) {
			return;
		}
		if ( ! isset( $this->options[$option_name] ) ) {
			add_filter( "pre_option_{$option_name}", array( $this, 'pre_get_option' ) );
		}
		$this->options[$option_name] = $new_value;
		return $old_value;
	}

	/**
	 * @param $value
	 * @return mixed
	 */
	function pre_get_option( $value ) {
		$option_name = preg_replace( '/^pre_option_/', '', current_filter() );
		if ( isset( $this->options[$option_name] ) ) {
			$value = $this->options[$option_name];
			$value = apply_filters( 'option_' . $option_name, $value );
		}
		return $value;
	}

	/**
	 * Undo any changes to the options since start() was called
	 */
	function stop() {
		if ( ! $this->_is_current ) {
			return;
		}
		remove_filter( 'pre_update_option', array( $this, 'pre_update_option' ), 10, 3 );
		foreach ( array_keys( $this->options ) as $option_name ) {
			remove_filter( "pre_option_{$option_name}", array( $this, 'pre_get_option' ) );
		}
		$this->options     = array();
		$this->_is_current = false;
	}

	function __destruct() {
		$this->stop();
	}
}
