<?php
/**
 * @category   Cybersource
 * @package    Wsu_Centralprocessing
 */
class Wsu_Centralprocessing_Block_Info extends Mage_Payment_Block_Info {
    protected function _construct() {
        parent::_construct();
        //$this->setTemplate('wsu/centralprocessing/info.phtml');
    }

    public function getMethodCode() {
        return $this->getInfo()->getMethodInstance()->getCode();
    }
	public function toPdf() {
		$this->setIsPdf(true);
		return parent::toPdf();
	}	
    protected function _prepareSpecificInformation($transport = null){
		$helper				= Mage::helper('centralprocessing');
        if (!is_null($this->_paymentSpecificInformation)) {
            return $this->_paymentSpecificInformation;
        }
		
        $info = $this->getInfo();

        $transport = new Varien_Object();
        $transport = parent::_prepareSpecificInformation($transport);
		
		$isAdminBlock = $this->getParentBlock() && $this->getParentBlock() instanceof Mage_Adminhtml_Block_Sales_Order_Payment;

		$transData=array();
		$BlockName = str_replace('.','_',$this->getNameInLayout());
		$ControllerName = $this->getRequest()->getControllerName();
		$ActionName = $this->getRequest()->getActionName();
		$RouteName = $this->getRequest()->getRouteName();
		$ModuleName = $this->getRequest()->getModuleName();
		
		$fullpath = $RouteName.'_'.$ControllerName.'_'.$ActionName;
		//echo '<!--';
		//var_dump($fullpath);
		//echo '-<<-route/controll/action | block ->>-';
		//var_dump($BlockName);
		//echo '-->';
		
		if ($isAdminBlock) {
			$transData[Mage::helper('payment')->__('Card Type')]=$helper->getCardType($info->getCardType());
			$transData[Mage::helper('payment')->__('Masked CC Number')]='############'.$info->getMaskedCcNumber();
			
			
			$GUID = $info->getResponseGuid();
			$mode = $info->getCcMode();
			$GUIDinfo="";//$helper->getResponseGuidInfo($GUID,($mode=="live"?1:0));
	
			$transData[Mage::helper('payment')->__('Response Return Code')]="".$info->getResponseReturnCode();
			$transData[Mage::helper('payment')->__('GUID')]=$GUID.$GUIDinfo;
			$transData[Mage::helper('payment')->__('Approval Code')]=$info->getApprovalCode();
			$transData[Mage::helper('payment')->__('CC Mode')]=$mode;
		}
		if (!$isAdminBlock && !$this->getIsPdf() && $fullpath!="checkout_multishipping_overview") {
			$transData[Mage::helper('payment')->__('Card Type')]=$helper->getCardType($info->getCardType());
			$transData[Mage::helper('payment')->__('Approval Code')]=$info->getApprovalCode();
		}		
		if($fullpath=="checkout_multishipping_overview"){
			$transData[" "]="(You will be redirected to the payment page)";
		}

        $transport->addData($transData);

        return $transport;
    }

	
	
}