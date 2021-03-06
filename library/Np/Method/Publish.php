<?php

/**
 * Np_Method_Publish File
 * 
 * @package         Np_Method
 * @subpackage      Np_Method_Publish
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero Public License version 3 or later; see LICENSE.txt
 */

/**
 * Np_Method_Publish Class Definition
 * 
 * @package Np_Method
 * @subpackage Np_Method_Publish
 */
class Np_Method_Publish extends Np_Method {

	/**
	 * Constructor
	 * 
	 * receives options array and sets into body array accordingly
	 * sets parent's $type to "Publish"
	 * 
	 * @param array $options 
	 */
	protected function __construct(&$options) {

		parent::__construct($options);
		//SET BODY 
		foreach ($options as $key => $value) {
			switch (ucwords(strtolower($key))) {
				case "Donor":
				case "Network_type":
				case "Number":
				case "From_number":
				case "To_number":
				case "Disconnect_time":
				case "Connect_time":
				case "Publish_type":
					$this->setBodyField($key, $value);
					break;
				case "Phone_number":
					$this->setBodyField('Number', $value);
					break;
			}
		}
	}

	/**
	 * overrridden from np_method
	 * 
	 * @return TRUE 
	 */
	protected function ValidateDB() {
		return true;
	}

	/**
	 * overridden from np_method
	 * 
	 * @return mixed string Reject Reason Code or TRUE 
	 */
	public function PostValidate() {
		$this->setAck($this->validateParams($this->getHeaders()));

		//HOW TO CHECK Gen05
		if (!$this->ValidateDB()) {
			return "Gen07";
		}
		
		$providers = Application_Model_General::getProviderArray(true);
		if (!in_array($this->getBodyField('DONOR'), $providers)) {
			return 'Pub05';
		}

		if (($timer_ack = Np_Timers::validate($this)) !== TRUE) {
			return $timer_ack;
		}
		return true;
	}

	/**
	 * overridden from parent
	 * 
	 * inserts row to database
	 * 
	 * @return type 
	 */
	public function saveToDB() {
		//this is a request from provider!
		//save a new row in Requests DB
		if ($this->getHeaderField("FROM") != Application_Model_General::getSettings('InternalProvider')) {
			try {
				$flags = new stdClass();
				$flags->publish_type = $this->getBodyField("PUBLISH_TYPE");
				$tbl = new Application_Model_DbTable_Requests(Np_Db::master());
				$data = array(
					'request_id' => $this->getHeaderField("REQUEST_ID"),
					'from_provider' => $this->getHeaderField("TO"),
					'to_provider' => $this->getHeaderField("FROM"),
					'status' => 1,
					'last_transaction' => $this->getHeaderField("MSG_TYPE"),
					'phone_number' => $this->getBodyField("NUMBER"),
					'disconnect_time' => Application_Model_General::getDateTimeInSqlFormat($this->getBodyField("DISCONNECT_TIME")),
					'connect_time' => Application_Model_General::getDateTimeInSqlFormat($this->getBodyField("CONNECT_TIME")),
					'transfer_time' => Application_Model_General::getDateTimeInSqlFormat(),
					'flags' => json_encode($flags),
				);

				return $tbl->insert($data);
			} catch (Exception $e) {
				error_log("Error on create record in transactions table: " . $e->getMessage());
			}
		}
		return TRUE;
	}

	public function createXml() {
		$xml = parent::createXml();
		$msgType = $this->getHeaderField('MSG_TYPE');
		$networkType = Application_Model_General::getSettings("NetworkType");
		$xml->$msgType->donor = $this->getBodyField('DONOR');
		$xml->$msgType->connectDateTime = Application_Model_General::getDateTimeIso($this->getBodyField('CONNECT_TIME'));
		$xml->$msgType->publishType = $this->getBodyField('PUBLISH_TYPE');
		$xml->$msgType->disconnectDateTime = Application_Model_General::getDateTimeIso($this->getBodyField('DISCONNECT_TIME'));
		if ($networkType === "M") {
			$xml->$msgType->mobile;
			$xml->$msgType->mobile->numberType = "I";
			$xml->$msgType->mobile->number = $this->getBodyField("NUMBER");
		} else {
			$xml->$msgType->fixed->fixedNumberSingle;
		}
		return $xml;
	}
	
	/**
	 * convert Xml data to associative array
	 * 
	 * @param simple_xml $xmlObject simple xml object
	 * 
	 * @return array converted data from hierarchical xml to flat array
	 */
	public function convertArray($xmlObject) {
		$ret = array();
		$ret['DONOR'] = (string) $xmlObject->donor;
		$ret['CONNECT_TIME'] = (string) $xmlObject->connectDateTime;
		$ret['PUBLISH_TYPE'] = (string) $xmlObject->publishType;
		$ret['DISCONNECT_TIME'] = (string) $xmlObject->disconnectDateTime;
		if (isset($xmlObject->fixed)) {
			$ret['NUMBER_TYPE'] = (string) $xmlObject->fixed->fixedNumberSingle->numberType;
			if (isset($xmlObject->fixed->fixedNumberRange)) {
				$ret['NUMBER_TYPE'] = (string) $xmlObject->fixed->fixedNumberRange->numberType;
				$ret['FROM_NUMBER'] = (string) $xmlObject->fixed->fixedNumberRange->fromNumber;
				$ret['TO_NUMBER'] = (string) $xmlObject->fixed->fixedNumberRange->toNumber;
			} else {
				$ret['NUMBER'] = (string) $xmlObject->fixed->fixedNumberSingle->number;
				$ret['PHONE_NUMBER'] = (string) $xmlObject->fixed->fixedNumberSingle->number;
			}
		} else {
			$ret['NUMBER_TYPE'] = (string) $xmlObject->mobile->numberType;
			$ret['NUMBER'] = (string) $xmlObject->mobile->number;
			$ret['PHONE_NUMBER'] = (string) $xmlObject->mobile->number;
		}
		return $ret;

	}


}
