<?php
//
// Description
// ===========
// This method will return the detail for a tax for a business.
//
// Arguments
// ---------
// 
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_sapos_invoiceItemGet(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'item_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Invoice Item'), 
        'taxtypes'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Include Taxtypes'), 
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
    $rc = ciniki_sapos_checkAccess($ciniki, $args['business_id'], 'ciniki.sapos.invoiceItemGet'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }
	$modules = $rc['modules'];

//	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'timezoneOffset');
//	$utc_offset = ciniki_businesses_timezoneOffset($ciniki);
//	ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
//	$date_format = ciniki_users_dateFormat($ciniki);

	//
	// Get the item details
	//
	$strsql = "SELECT id, line_number, "
		. "status, "
		. "object, "
		. "object_id, "
		. "description, "
		. "quantity, "
		. "ROUND(unit_amount, 2) AS unit_amount, "
		. "ROUND(unit_discount_amount, 2) AS unit_discount_amount, "
		. "unit_discount_percentage, "
		. "ROUND(subtotal_amount, 2) AS subtotal_amount, "
		. "ROUND(discount_amount, 2) AS discount_amount, "
		. "ROUND(total_amount, 2) AS total_amount, "
		. "taxtype_id, "
		. "notes "
		. "FROM ciniki_sapos_invoice_items "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
		. "";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.sapos', 'item');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['item']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'1381', 'msg'=>'Unable to find invoice item.'));
	}
	$item = $rc['item'];

	$item['quantity'] = (float)$item['quantity'];
	$item['unit_discount_percentage'] = (float)$item['unit_discount_percentage'];
	$item['unit_discount_amount'] = (float)$item['unit_discount_amount'];

	//
	// Get the tax types available for the business
	//
	if( isset($modules['ciniki.taxes']) && isset($args['taxtypes']) && $args['taxtypes'] == 'yes' ) {
		ciniki_core_loadMethod($ciniki, 'ciniki', 'taxes', 'private', 'taxTypes');
		$rc = ciniki_taxes_taxTypes($ciniki, $args['business_id']);
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		return array('stat'=>'ok', 'item'=>$item, 'taxtypes'=>$rc['types']);
	}

	return array('stat'=>'ok', 'item'=>$item);
}
?>