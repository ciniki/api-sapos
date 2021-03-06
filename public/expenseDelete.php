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
//
function ciniki_sapos_expenseDelete(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'expense_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Expense'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'sapos', 'private', 'checkAccess');
    $rc = ciniki_sapos_checkAccess($ciniki, $args['tnid'], 'ciniki.sapos.expenseDelete'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

    //
    // Load the expense record
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'sapos', 'private', 'expenseLoad');
    $rc = ciniki_sapos_expenseLoad($ciniki, $args['tnid'], $args['expense_id']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $expense = $rc['expense'];

    //
    // Start the transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.sapos');
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

    //
    // Remove the items
    //
    if( isset($expense['items']) && count($expense['items']) > 0 ) {
        foreach($expense['items'] as $iid => $item) {
            $rc = ciniki_core_objectDelete($ciniki, $args['tnid'], 'ciniki.sapos.expense_item', 
                $item['id'], NULL, 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.sapos');
                return $rc;
            }
        }
    }

    //
    // Remove the expense
    //
    $rc = ciniki_core_objectDelete($ciniki, $args['tnid'], 'ciniki.sapos.expense', 
        $args['expense_id'], NULL, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.sapos');
        return $rc;
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.sapos');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.sapos');
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'sapos');

    return array('stat'=>'ok');
}
?>
