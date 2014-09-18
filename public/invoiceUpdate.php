<?php
//
// Description
// ===========
//
// Arguments
// ---------
// 
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_sapos_invoiceUpdate(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'invoice_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Invoice'), 
        'customer_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Customer'), 
        'salesrep_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Salesrep'), 
		'invoice_number'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Invoice Number'),
		'invoice_type'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Invoice Type'),
		'po_number'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'PO Number'),
		'status'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Status'),
		'payment_status'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Payment Status'),
		'shipping_status'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Shipping Status'),
		'manufacturing_status'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Manufacturing Status'),
		'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
		'invoice_date'=>array('required'=>'no', 'blank'=>'no', 'type'=>'datetimetoutc', 'name'=>'Invoice Date'),
		'due_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'datetimetoutc', 'name'=>'Due Date'),
		'billing_update'=>array('required'=>'no', 'blank'=>'yes', 'default'=>'no', 'name'=>'Update Billing from Customer'),
		'billing_name'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Billing Name'),
		'billing_address1'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Billing Address Line 1'),
		'billing_address2'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Billing Address Line 2'),
		'billing_city'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Billing City'),
		'billing_province'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Billing Province'),
		'billing_postal'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Billing Postal'),
		'billing_country'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Billing Country'),
		'shipping_update'=>array('required'=>'no', 'blank'=>'yes', 'default'=>'no', 'name'=>'Update Shipping from Customer'),
		'shipping_name'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Shipping Name'),
		'shipping_address1'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Shipping Address Line 1'),
		'shipping_address2'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Shipping Address Line 2'),
		'shipping_city'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Shipping City'),
		'shipping_province'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Shipping Province'),
		'shipping_postal'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Shipping Postal'),
		'shipping_country'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Shipping Country'),
		'tax_location_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Tax Location'),
		'pricepoint_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Pricepoint'),
		'action'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Action'),
		'customer_notes'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Customer Notes'),
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
    // Make sure this module is activated, and
    // check permission to run this function for this business
    //  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'sapos', 'private', 'checkAccess');
    $rc = ciniki_sapos_checkAccess($ciniki, $args['business_id'], 'ciniki.sapos.invoiceUpdate'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');

	//
	// Get the existing invoice details to compare fields
	//
	$strsql = "SELECT invoice_number, customer_id, salesrep_id, tax_location_id, pricepoint_id "
		. "FROM ciniki_sapos_invoices "
		. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['invoice_id']) . "' "
		. "AND business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.sapos', 'invoice');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['invoice']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2011', 'msg'=>'Unable to find invoice'));
	}
	$invoice = $rc['invoice'];

	//
	// Check to make sure the invoice belongs to the salesrep, if they aren't also owners/employees
	//
	if( isset($ciniki['business']['user']['perms']) && ($ciniki['business']['user']['perms']&0x07) == 0x04 ) {
		$strsql = "SELECT id "
			. "FROM ciniki_sapos_invoices "
			. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $item['invoice_id']) . "' "
			. "AND business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
			. "AND salesrep_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
			. "";
		$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.sapos', 'invoice');
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		if( !isset($rc['invoice']) ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2029', 'msg'=>'Permission denied'));
		}
	}

	//
	// If a customer is specified, then lookup the customer details and fill out the invoice
	// based on the customer.  
	//
	if( isset($args['customer_id']) && $args['customer_id'] > 0 ) {
		$strsql = "SELECT ciniki_customers.id, ciniki_customers.type, ciniki_customers.display_name, "
			. "ciniki_customers.company, "
			. "ciniki_customers.salesrep_id, "
			. "ciniki_customers.tax_location_id, "
			. "ciniki_customers.pricepoint_id, "
			. "ciniki_customer_addresses.id AS address_id, "
			. "ciniki_customer_addresses.flags, "
			. "ciniki_customer_addresses.address1, "
			. "ciniki_customer_addresses.address2, "
			. "ciniki_customer_addresses.city, "
			. "ciniki_customer_addresses.province, "
			. "ciniki_customer_addresses.postal, "
			. "ciniki_customer_addresses.country "
			. "FROM ciniki_customers "
			. "LEFT JOIN ciniki_customer_addresses ON (ciniki_customers.id = ciniki_customer_addresses.customer_id "
				. "AND ciniki_customer_addresses.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
				. ") "
			. "WHERE ciniki_customers.id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
			. "AND ciniki_customers.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
			. "";
		$rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.customers', array(
			array('container'=>'customers', 'fname'=>'id', 
				'fields'=>array('id', 'type', 'display_name', 'company', 
					'salesrep_id', 'tax_location_id', 'pricepoint_id')),
			array('container'=>'addresses', 'fname'=>'address_id',
				'fields'=>array('id'=>'address_id', 'flags', 'address1', 'address2', 'city', 'province', 'postal', 'country')),
			));
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		if( isset($rc['customers']) && isset($rc['customers'][$args['customer_id']]) ) {
			$customer = $rc['customers'][$args['customer_id']];
			if( isset($customer['salesrep_id']) && $customer['salesrep_id'] > 0 
				&& (!isset($args['salesrep_id']) && $invoice['salesrep_id'] == 0) 
				) {
				// Only set the salesrep_id if there isn't already one set.
				$args['salesrep_id'] = $customer['salesrep_id'];
			}
			if( isset($customer['tax_location_id']) && $customer['tax_location_id'] > 0 
				&& (!isset($args['tax_location_id']) && $invoice['tax_location_id'] == 0) 
				) {
				$args['tax_location_id'] = $customer['tax_location_id'];
			}
			if( isset($customer['pricepoint_id']) && $customer['pricepoint_id'] > 0 
				&& (!isset($args['pricepoint_id']) && $invoice['pricepoint_id'] == 0) 
				) {
				$args['pricepoint_id'] = $customer['pricepoint_id'];
			}
//			$rc['customers'][$args['customer_id']]['name'] = $customer['display_name'];

			if( (isset($args['billing_name']) && $args['billing_name'] == '') || $args['billing_update'] == 'yes' ) {
//				if( $customer['type'] == 2 ) {
//					$args['billing_name'] = $customer['company'];
//				} else {
					$args['billing_name'] = $customer['display_name'];
//				}
			}
			if( (isset($args['shipping_name']) && $args['shipping_name'] == '') || $args['shipping_update'] == 'yes' ) {
//				if( $customer['type'] == 2 ) {
//					$args['shipping_name'] = $customer['company'];
//				} else {
					$args['shipping_name'] = $customer['display_name'];
//				}
			}
			if( isset($customer['addresses']) ) {
				foreach($customer['addresses'] as $aid => $address) {
					if( ($address['flags']&0x01) == 0x01 
						&& ((isset($args['shipping_address1']) && $args['shipping_address1'] == '') 
							|| $args['shipping_update'] == 'yes') ) {
						$args['shipping_address1'] = $address['address1'];
						$args['shipping_address2'] = $address['address2'];
						$args['shipping_city'] = $address['city'];
						$args['shipping_province'] = $address['province'];
						$args['shipping_postal'] = $address['postal'];
						$args['shipping_country'] = $address['country'];
					}
					if( ($address['flags']&0x02) == 0x02 
						&& ((isset($args['billing_address1']) && $args['billing_address1'] == '' )
							|| $args['billing_update'] == 'yes') ) {
						$args['billing_address1'] = $address['address1'];
						$args['billing_address2'] = $address['address2'];
						$args['billing_city'] = $address['city'];
						$args['billing_province'] = $address['province'];
						$args['billing_postal'] = $address['postal'];
						$args['billing_country'] = $address['country'];
					}
				}
			}
		} else {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'1096', 'msg'=>'Unable to find customer'));
		}
	}

	if( isset($args['action']) && $args['action'] == 'submit' ) {
		$strsql = "SELECT invoice_type, status, shipping_status "
			. "FROM ciniki_sapos_invoices "
			. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['invoice_id']) . "' "
			. "AND business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
			. "";
		$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.sapos', 'invoice');
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		if( !isset($rc['invoice']) ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2006', 'msg'=>'Unable to find invoice'));
		}
		$invoice = $rc['invoice'];
		if( $invoice['invoice_type'] == 40 && $invoice['status'] == 10 ) {
			$args['status'] = 30;
		}
	}

	//
	// Start the transaction
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.sapos');
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}   

	//
	// Update the invoice
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
	$rc = ciniki_core_objectUpdate($ciniki, $args['business_id'], 'ciniki.sapos.invoice', 
		$args['invoice_id'], $args, 0x04);
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.sapos');
		return $rc;
	}

	//
	// Return the invoice record
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'sapos', 'private', 'invoiceLoad');
	$rc = ciniki_sapos_invoiceLoad($ciniki, $args['business_id'], $args['invoice_id']);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$invoice = $rc['invoice'];

	//
	// Check for callbacks
	//
	if( isset($invoice['items']) ) {
		foreach($invoice['items'] as $iid => $item) {
			$item = $item['item'];
			if( $item['object'] != '' && $item['object_id'] != '' ) {
				list($pkg,$mod,$obj) = explode('.', $item['object']);
				$rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'sapos', 'invoiceUpdate');
				if( $rc['stat'] == 'ok' ) {
					$fn = $rc['function_call'];
					$rc = $fn($ciniki, $args['business_id'], $invoice['id'], $item);
					if( $rc['stat'] != 'ok' ) {
						return $rc;
					}
				}
			}
		}
	}

	//
	// Update the taxes/shipping incase something relavent changed
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'sapos', 'private', 'invoiceUpdateShippingTaxesTotal');
	$rc = ciniki_sapos_invoiceUpdateShippingTaxesTotal($ciniki, $args['business_id'], $args['invoice_id']);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Update the status
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'sapos', 'private', 'invoiceUpdateStatusBalance');
	$rc = ciniki_sapos_invoiceUpdateStatusBalance($ciniki, $args['business_id'], $args['invoice_id']);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Reload the invoice record incase anything has changed
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'sapos', 'private', 'invoiceLoad');
	$rc = ciniki_sapos_invoiceLoad($ciniki, $args['business_id'], $args['invoice_id']);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$invoice = $rc['invoice'];

	//
	// Commit the transaction
	//
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.sapos');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.sapos');
		return $rc;
	}

	//
	// Update the last_change date in the business modules
	// Ignore the result, as we don't want to stop user updates if this fails.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
	ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'sapos');

	return array('stat'=>'ok', 'invoice'=>$invoice);
}
?>
