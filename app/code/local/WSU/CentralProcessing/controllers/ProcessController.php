<?php
/**
 * @category   Cybersource
 * @package    Wsu_CentralProcessing
 */
class Wsu_CentralProcessing_ProcessController extends Mage_Core_Controller_Front_Action {
    protected $_order;

    protected function _getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

   	protected function _expireAjax() {
        if (!$this->_getCheckout()->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }

    public function getCentralProcessing() {
        return Mage::getSingleton('centralprocessing/centralprocessing');
    }

    public function getOrder() {
        if ($this->_order == null) {
            $session = Mage::getSingleton('checkout/session');
            $this->_order = Mage::getModel('sales/order');
            $this->_order->loadByIncrementId($session->getLastRealOrderId());
        }
        return $this->_order;
    }

	public function redirectAction() {
		$session 	= $this->_getCheckout();
		$order 		= $this->getOrder();
		if (!$order->getId()) {
			$this->norouteAction();
			return;
		}

		$order->addStatusToHistory(
			$order->getStatus(),
			$this->__('Customer was redirected to Cybersource.')
		);
		$order->save();

		$this->getResponse()
			->setBody(
				$this->getLayout()->createBlock('centralprocessing/redirect')->setOrder($order)->toHtml()
			);
    }

    public function ipnAction() {
	   $helper				= Mage::helper('centralprocessing');
	   $request				= $this->getRequest();
	   $params				= $request->getParams();
	   $helper->log('ipnAction()::start');

	   //signature check...
	   if($this->_validateResponse($params)){
			$orderId = isset($params['req_reference_number']) ? $params['req_reference_number'] : null;
			$order	 = Mage::getModel('sales/order')->loadByIncrementId($orderId);
			if ($order && $order->canInvoice()) {
				$invoice = $order->prepareInvoice();
				$invoice->register()->capture();
				Mage::getModel('core/resource_transaction')
				   ->addObject($invoice)
				   ->addObject($invoice->getOrder())
				   ->save();
				$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
				$order->getPayment()->setLastTransId($params['transaction_id']);
				$order->sendNewOrderEmail();
				$order->setEmailSent(true);

				$order->save();
				$helper->log('ipnAction()::invoice-created, main sent');
			}
		}
    }

	protected function _validateResponse($params) {
		$helper = Mage::helper('centralprocessing');
		$helper->log('_validateResponse()::');
		$helper->log($params);

		$orderId = isset($params['req_reference_number']) ? $params['req_reference_number'] : null;
		$order	 = Mage::getModel('sales/order')->loadByIncrementId($orderId);
		if(!$order){
			return false;
		}
		$errors = array();
		if(isset($params['decision']) && $params['decision'] != 'ACCEPT'){
			$errors[] = 'decision is not ACCEPT';
		}
		if( isset($params['reason_code']) && !in_array($params['reason_code'], array(100, 110)) ){
			$errors[] = 'reason_code is not 100, 110';
		}

		$hashSign  = $helper->getHashSign($params);
		$signature = isset($params['signature']) ? $params['signature'] : null;
		if($hashSign != $signature){
			$errors[] = 'singature is invalid';
		}

		if(count($errors) == 0){
			return true;
		}else{
			return false;
		}
	}

    public function successAction() {
		$helper		   = Mage::helper('centralprocessing');
		$order         = $this->getOrder();
		if ( !$order->getId() ) {
			$this->_redirect('checkout/cart');
			return false;
		}

		$helper->log('successAction()::');
		$responseParams     = $this->getRequest()->getParams();
        $validateResponse	= $this->_validateResponse($responseParams);
		if($validateResponse){

			$order->addStatusToHistory(
				$order->getStatus(),
				$this->__('Customer successfully returned from CyberSource and the payment is APPROVED.')
			);
			#$order->sendNewOrderEmail(); //already sent above
			$order->save();

            $this->_redirect('checkout/onepage/success');
            return;
		}else{
			$comment = '';
			if(isset($responseParams['message'])){
				$comment .= '<br />Error: ';
				$comment .= "'" . $responseParams['message'] . "'";
			}
			$order->cancel();
            $order->addStatusToHistory(
				$order->getStatus(),
				$this->__('Customer successfully returned from CyberSource but the payment is DECLINED.') . $comment
			);
			$order->save();

			$this->_getCheckout()->addError($this->__('There is an error processing your payment.' . $comment));
			$this->_redirect('checkout/cart');
    	    return;
		}
    }

    public function cancelAction() {
		$order         = $this->getOrder();
		if ( !$order->getId() ) {
			$this->_redirect('checkout/cart');
			return false;
		}

        $order->cancel();
        $order->addStatusToHistory(
			$order->getStatus(),
			$this->__('Payment was canceled.')
		);
        $order->save();

		$this->_getCheckout()->addError($this->__('Payment was canceled.'));
		$this->_redirect('checkout/cart');
	}

    public function failureAction() {
		if($this->getConfigData('clear_cart_oncancel')){
			//we are going to wipe the cart
			$order         = $this->getOrder();
			if ( !$order->getId() ) {
				$this->_redirect('checkout/cart');
				return false;
			}
	
			$order->cancel();
			$order->addStatusToHistory(
				$order->getStatus(),
				$this->__('Payment failed.')
			);
			$order->save();
			
			$this->_getCheckout()->addError($this->__('Payment failed.'));
			$this->_redirect('checkout/cart');
		}else{
			//we are going to try to rebuild the cart for the user
			$lastQuoteId = $this->getOnepage()->getCheckout()->getLastQuoteId();
			$lastOrderId = $this->getOnepage()->getCheckout()->getLastOrderId();
		
		
			if ($lastQuoteId && $lastOrderId) {
				$orderModel = Mage::getModel('sales/order')->load($lastOrderId);
				if($orderModel->canCancel()) {
					
					$quote = Mage::getModel('sales/quote')->load($lastQuoteId);
					$quote->setIsActive(true)->save();
					
					$orderModel->cancel();
					$orderModel->setStatus('canceled');
					$orderModel->save();
		
					Mage::getSingleton('core/session')->setFailureMsg('order_failed');
					Mage::getSingleton('checkout/session')->setFirstTimeChk('0');
					$this->_redirect('checkout/cart');
					return;
				}
			}
			if (!$lastQuoteId || !$lastOrderId) {
				$this->_redirect('checkout/cart');
				return;
			}
		
			$this->loadLayout();
			$this->renderLayout();
		}
	

    }
}