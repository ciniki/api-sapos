<?php
//
// Description
// ===========
// This method will produce a PDF of the invoice.
//
// Arguments
// ---------
// 
// Returns
// -------
//
function ciniki_sapos_quotePDF(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'invoice_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Invoice'), 
        'type'=>array('required'=>'no', 'blank'=>'yes', 'default'=>'invoice', 'name'=>'Output Type'),
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
    $rc = ciniki_sapos_checkAccess($ciniki, $args['tnid'], 'ciniki.sapos.quotePDF'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

    //
    // Load tenant details
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'tenantDetails');
    $rc = ciniki_tenants_tenantDetails($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['details']) && is_array($rc['details']) ) {    
        $tenant_details = $rc['details'];
    } else {
        $tenant_details = array();
    }

    //
    // Load the invoice settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
    $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_sapos_settings', 'tnid', $args['tnid'],
        'ciniki.sapos', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['settings']) ) {
        $sapos_settings = $rc['settings'];
    } else {
        $sapos_settings = array();
    }
    
    //
    // check for invoice-default-template
    //
    if( !isset($sapos_settings['invoice-default-template']) 
        || $sapos_settings['invoice-default-template'] == '' ) {
        $quote_template = 'quoteDefault';
    } else {
        $quote_template = $sapos_settings['quote-default-template'];
    }
    
    $rc = ciniki_core_loadMethod($ciniki, 'ciniki', 'sapos', 'templates', $quote_template);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $fn = $rc['function_call'];

    return $fn($ciniki, $args['tnid'], $args['invoice_id'],
        $tenant_details, $sapos_settings);
}
?>
