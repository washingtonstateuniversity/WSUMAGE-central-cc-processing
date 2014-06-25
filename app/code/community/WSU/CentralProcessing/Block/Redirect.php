<?php
/**
 * @category   Cybersource
 * @package    Wsu_CentralProcessing
 */
class Wsu_CentralProcessing_Block_Redirect extends Mage_Core_Block_Abstract {
	
	

	private function removeResponseXMLNS($input) { 
		// Remove XML response namespaces one by one 
		$input = str_replace(' xmlns="webservice.it.wsu.edu"','',$input); 
		$input = str_replace(' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"','',$input); 
		return str_replace(' xmlns:xsd="http://www.w3.org/2001/XMLSchema"','',$input); 
	} 

	
	
	
	protected function _toHtml() {
	$standard 	= $this->getOrder()->getPayment()->getMethodInstance();
	$helper				= Mage::helper('centralprocessing');
        $form 		= new Varien_Data_Form();
        $form->setAction($helper->getCentralProcessingUrl())
            ->setId('centralprocessing_payment_checkout')
            ->setName('centralprocessing_payment_checkout')
            ->setMethod('POST')
            ->setUseContainer(true);
		$formFields = $standard->getFormFields();
		foreach ($formFields as $field => $value) {
            $form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
        }




		//url-ify the data for the POST
		$fields_string="";
		$url = trim($helper->getCentralProcessingUrl(),'/');
		$url .= DS.($helper->getAuthorizationType()=="AUTHCAP"?"AuthCapRequestWithCancelURL":"AuthRequestWithCancelURL");
		
		
		foreach($formFields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
		rtrim($fields_string, '&');
		
		
		$wrapper = fopen('php://temp', 'r+');
		
		//open connection
		$ch = curl_init($url);
		
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_STDERR, $wrapper);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_POST, count($formFields));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
		
		//execute post
		$result = curl_exec($ch);
		if($result === false) {
			echo 'Curl error: ' . curl_error($ch);
		}
		
		
		//close connection
		curl_close($ch);
		var_dump($url);
		var_dump($fields_string);
		

		var_dump($result);
		
		$nodes = new SimpleXMLElement($this->removeResponseXMLNS($result));
		
		$urlRedirect = $nodes->WebPageURLAndGUID;
		
		var_dump($urlRedirect);
		
		header("Location: ".$urlRedirect);
		exit();
    }
}