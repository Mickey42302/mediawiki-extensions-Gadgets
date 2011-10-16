<?php

/**
 * 
 * API for setting Gadget's preferences
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

// TODO: Needs to be changed for new backend
class ApiSetGadgetPrefs extends ApiBase {

	public function execute() {
		$user = RequestContext::getMain()->getUser();
		
		$params = $this->extractRequestParams();
		//Check permissions
		if ( !$user->isLoggedIn() ) {
			$this->dieUsage( 'You must be logged-in to set gadget\'s preferences', 'notloggedin' );
		}

		//Check token
		if ( !$user->matchEditToken( $params['token'] ) ) {
			$this->dieUsageMsg( 'sessionfailure' );
		}

		$gadgetName = $params['gadget'];
		$gadgets = Gadget::loadList();
		$gadget = $gadgets && isset( $gadgets[$gadgetName] ) ? $gadgets[$gadgetName] : null;
		
		if ( $gadget === null ) {
			$this->dieUsage( 'Gadget not found', 'notfound' );
		}

		$prefsJson = $params['prefs'];
		$prefs = FormatJson::decode( $prefsJson, true );
		
		if ( !is_array( $prefs ) ) {
			$this->dieUsage( 'The \'pref\' parameter must be valid JSON', 'notjson' );
		}

		$result = $gadget->setPrefs( $prefs, true );

		if ( $result === true ) {
			$this->getResult()->addValue(
				null, $this->getModuleName(), array( 'result' => 'Success' ) );
		} else {
			$this->dieUsage( 'Invalid preferences', 'invalidprefs' );
		}
	}

	public function mustBePosted() {
		return true;
	}
	
	public function isWriteMode() {
		return true;
	}

	public function getAllowedParams() {
		return array(
			'gadget'   	=> array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'prefs'   	=> array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'token'   	=> array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
		);
	}

	public function getParamDescription() {
		return array(
			'gadget'  	=> 'The name of the gadget',
			'prefs'   	=> 'The new preferences in JSON format',
			'token'   	=> 'An edit token'
		);
	}

	public function getDescription() {
		return 'Allows user code to set preferences for gadgets';
	}

	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'notloggedin', 'info' => 'You must be logged-in to get gadget\'s preferences' ),
			array( 'sessionfailure' ),
			array( 'code' => 'notfound', 'info' => 'Gadget not found' ),
			array( 'code' => 'notjson', 'info' => 'The \'pref\' parameter must be valid JSON' ),
			array( 'code' => 'invalidprefs', 'info' => 'Invalid preferences' ),
		) );
	}

	public function needsToken() {
		return true;
	}
	
	public function getSalt() {
		return '';
	}

	public function getVersion() {
		return __CLASS__ . ': $Id: ApiSetGadgetPrefs.php 90469 2011-06-20 16:42:35Z salvatoreingala $';
	}
}
