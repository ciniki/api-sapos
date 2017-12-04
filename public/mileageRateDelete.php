<?php
//
// Description
// ===========
// This method will delete and existing mileage rate for a tenant.
//
// Arguments
// ---------
// 
// Returns
// -------
//
function ciniki_sapos_mileageRateDelete(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'rate_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Mileage Rate'), 
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
    $rc = ciniki_sapos_checkAccess($ciniki, $args['tnid'], 'ciniki.sapos.mileageRateDelete'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }
    $modules = $rc['modules'];

    //
    // Update the mileage rate
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
    $rc = ciniki_core_objectDelete($ciniki, $args['tnid'], 'ciniki.sapos.mileage_rate', 
        $args['rate_id'], NULL, 0x07);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    return $rc;
}
?>
