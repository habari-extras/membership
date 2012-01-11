<?php

class Membership extends Plugin
{
	public function configure()
	{
		$ui = new FormUI( "membership" );
		$tokens = $ui->append( 'textarea', 'tokens', 'option:membership__tokens', _t( 'Tokens, one per line', 'membership' ) );
		$tokens->rows = 4;
		$tokens->class[] = 'resizable';
		$tokens->add_validator( array( $this, "duplicate_validator" ) );
		$ui->on_success( array( $this, 'updated_config' ) );
		$ui->append( 'submit', 'save', _t( 'Save', 'membership' ) );
		return $ui;
	}

	public function duplicate_validator( $list, $control, $form )
	{
		$values = explode( "\n", $list );
		$values = array_map( 'trim', $values );
		$dupes = array_keys( array_filter( array_count_values( $values ), create_function( '$v', 'return $v > 1;' ) ) );

		if( count( $dupes ) ) {
			return array( _t( 'Tokens must be unique. There is more than one: %s', array( implode( ", ", $dupes ) ), 'membership' ) );
		}
		else {
			return array();
		}
	}

	public function updated_config( FormUI $ui )
	{
		Session::notice( _t( 'Tokens saved.' , 'membership' ) );
		$ui->save();
	}

}
?>
