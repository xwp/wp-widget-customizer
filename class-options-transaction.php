<?php
/**
 * Allow changes to options to be logged and rolled back
 */

class Options_Transaction {

	/**
	 * @var array $options values updated while transaction is open
	 */
	public $options = array();

	protected $_ignore_transients = true;
	protected $_is_current = false;
	protected $_operations = array();

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
	 * Get the number of operations performed in the transaction
	 * @return bool
	 */
	function count() {
		return count( $this->_operations );
	}

	/**
	 * Start keeping track of changes to options, and cache their new values
	 */
	function start() {
		$this->_is_current = true;
		add_action( 'added_option', array( $this, '_capture_added_option' ), 10, 2 );
		add_action( 'updated_option', array( $this, '_capture_updated_option' ), 10, 3 );
		add_action( 'delete_option', array( $this, '_capture_pre_deleted_option' ), 10, 1 );
		add_action( 'deleted_option', array( $this, '_capture_deleted_option' ), 10, 1 );
	}

	/**
	 * @action added_option
	 * @param $option_name
	 * @param $new_value
	 */
	function _capture_added_option( $option_name, $new_value ) {
		if ( $this->is_option_ignored( $option_name ) ) {
			return;
		}
		$this->options[$option_name] = $new_value;
		$operation = 'add';
		$this->_operations[] = compact( 'operation', 'option_name', 'new_value' );
	}

	/**
	 * @action updated_option
	 * @param string $option_name
	 * @param mixed $old_value
	 * @param mixed $new_value
	 */
	function _capture_updated_option( $option_name, $old_value, $new_value ) {
		if ( $this->is_option_ignored( $option_name ) ) {
			return;
		}
		$this->options[$option_name] = $new_value;
		$operation = 'update';
		$this->_operations[] = compact( 'operation', 'option_name', 'old_value', 'new_value' );
	}

	protected $_pending_delete_option_autoload;
	protected $_pending_delete_option_value;

	/**
	 * It's too bad the old_value and autoload aren't passed into the deleted_option action
	 * @action delete_option
	 * @param string $option_name
	 */
	function _capture_pre_deleted_option( $option_name ) {
		if ( $this->is_option_ignored( $option_name ) ) {
			return;
		}
		global $wpdb;
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option_name ) ); // db call ok; no-cache ok
		$this->_pending_delete_option_autoload = $autoload;
		$this->_pending_delete_option_value    = get_option( $option_name );
	}

	/**
	 * @action deleted_option
	 * @param string $option_name
	 */
	function _capture_deleted_option( $option_name ) {
		if ( $this->is_option_ignored( $option_name ) ) {
			return;
		}
		unset( $this->options[$option_name] );
		$operation = 'delete';
		$old_value = $this->_pending_delete_option_value;
		$autoload  = $this->_pending_delete_option_autoload;
		$this->_operations[] = compact( 'operation', 'option_name', 'old_value', 'autoload' );
	}

	/**
	 * Undo any changes to the options since start() was called
	 */
	function rollback() {
		remove_action( 'updated_option', array( $this, '_capture_updated_option' ), 10, 3 );
		remove_action( 'added_option', array( $this, '_capture_added_option' ), 10, 2 );
		remove_action( 'delete_option', array( $this, '_capture_pre_deleted_option' ), 10, 1 );
		remove_action( 'deleted_option', array( $this, '_capture_deleted_option' ), 10, 1 );
		while ( 0 !== count( $this->_operations ) ) {
			$option_operation = array_pop( $this->_operations );
			if ( 'add' === $option_operation['operation'] ) {
				delete_option( $option_operation['option_name'] );
			}
			else if ( 'delete' === $option_operation['operation'] ) {
				add_option( $option_operation['option_name'], $option_operation['old_value'], null, $option_operation['autoload'] );
			}
			else if ( 'update' === $option_operation['operation'] ) {
				update_option( $option_operation['option_name'], $option_operation['old_value'] );
			}
			else {
				throw new Exception( 'Unexpected operation' );
			}
		}
		$this->_is_current = false;
	}
}
