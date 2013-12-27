<?php
//
// Description
// -----------
// This function will load an expense and all the pieces for it.
//
// Arguments
// ---------
// ciniki:
// business_id:			The business ID to check the session user against.
// method:				The requested method.
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_sapos_expenseLoad($ciniki, $business_id, $expense_id, $images) {
	//
	// Get the time information for business and user
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
	$rc = ciniki_businesses_intlSettings($ciniki, $business_id);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$intl_timezone = $rc['settings']['intl-default-timezone'];
	$intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
	$intl_currency = $rc['settings']['intl-default-currency'];

//	ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'timeFormat');
//	$time_format = ciniki_users_timeFormat($ciniki, 'php');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
	$date_format = ciniki_users_dateFormat($ciniki);
//	ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'datetimeFormat');
//	$datetime_format = ciniki_users_datetimeFormat($ciniki, 'php');
	
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryTree');

	//
	// The the expense details
	//
	$strsql = "SELECT id, "
		. "name, "
		. "description, "
		. "IFNULL(DATE_FORMAT(ciniki_sapos_expenses.invoice_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "'), '') AS invoice_date, "
		. "IFNULL(DATE_FORMAT(ciniki_sapos_expenses.paid_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "'), '') AS paid_date, "
		. "total_amount, "
		. "notes "
		. "FROM ciniki_sapos_expenses "
		. "WHERE ciniki_sapos_expenses.id = '" . ciniki_core_dbQuote($ciniki, $expense_id) . "' "
		. "AND ciniki_sapos_expenses.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "";
	$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.sapos', array(
		array('container'=>'expenses', 'fname'=>'id', 'name'=>'expense',
			'fields'=>array('id', 'name', 'description', 
				'invoice_date', 'paid_date', 
				'total_amount', 'notes'),
//			'utctotz'=>array('invoice_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
//				'invoice_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
//				'invoice_datetime'=>array('timezone'=>$intl_timezone, 'format'=>$datetime_format),
//				'paid_date'=>array('timezone'=>$intl_timezone, 'format'=>$date_format),
//				'paid_time'=>array('timezone'=>$intl_timezone, 'format'=>$time_format),
//				'paid_datetime'=>array('timezone'=>$intl_timezone, 'format'=>$datetime_format),
//				),
			),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['expenses']) || !isset($rc['expenses'][0]['expense']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'1083', 'msg'=>'Expense does not exist'));
	}
	$expense = $rc['expenses'][0]['expense'];

	//
	// Get the item details
	//
	$strsql = "SELECT ciniki_sapos_expense_items.id, "	
		. "ciniki_sapos_expense_items.category_id, "
		. "ciniki_sapos_expense_categories.name, "
		. "ciniki_sapos_expense_items.amount, "
		. "ciniki_sapos_expense_items.notes "
		. "FROM ciniki_sapos_expense_items "
		. "LEFT JOIN ciniki_sapos_expense_categories ON (ciniki_sapos_expense_items.category_id = ciniki_sapos_expense_categories.id "
			. "AND ciniki_sapos_expense_categories.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. ") "
		. "WHERE ciniki_sapos_expense_items.expense_id = '" . ciniki_core_dbQuote($ciniki, $expense_id) . "' "
		. "AND ciniki_sapos_expense_items.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "ORDER BY ciniki_sapos_expense_categories.sequence "
		. "";
	$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.sapos', array(
		array('container'=>'items', 'fname'=>'id', 'name'=>'item',
			'fields'=>array('id', 'category_id', 'name', 'amount', 'notes')),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['items']) ) {
		$expense['items'] = array();
	} else {
		$expense['items'] = $rc['items'];
		foreach($expense['items'] as $iid => $item) {
			$expense['items'][$iid]['item']['amount'] = numfmt_format_currency(
				$intl_currency_fmt, $item['item']['amount'], $intl_currency);
		}
	}

	//
	// Get the images
	//
	if( $images == 'yes' ) {
		$strsql = "SELECT id, image_id "
			. "FROM ciniki_sapos_expense_images "
			. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. "AND expense_id = '" . ciniki_core_dbQuote($ciniki, $expense_id) . "' "
			. "";
		$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.sapos', array(
			array('container'=>'images', 'fname'=>'id', 'name'=>'image',
				'fields'=>array('id', 'image_id')),
			));
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		if( isset($rc['images']) ) {
			$images = $rc['images'];
			ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'loadCacheThumbnail');
			foreach($images as $iid => $img ) {
				if( $img['image']['image_id'] > 0 ) {
					$rc = ciniki_images_loadCacheThumbnail($ciniki, $img['image']['image_id'], 75);
					if( $rc['stat'] != 'ok' ) {
						return $rc;
					}
					$images[$iid]['image']['image_data'] = 'data:image/jpg;base64,' . base64_encode($rc['image']);
				}
			}
			$expense['images'] = $images;
		} else {
			$expense['images'] = array();
		}

	}

	//
	// Format the currency numbers
	//
	$expense['total_amount'] = numfmt_format_currency($intl_currency_fmt, 
		$expense['total_amount'], $intl_currency);

	return array('stat'=>'ok', 'expense'=>$expense);
}
?>