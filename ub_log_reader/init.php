<?php
/**
 * The address of the page that searches the logs. Who may read it is
 * decided by the controller for each request (UB_Log_Reader::allowed());
 * everybody else gets "not found", as if there were no such address.
 */
Route::set('ub-log-reader', 'ub-log-reader')
	->defaults([
		'directory'  => 'UB/Log',
		'controller' => 'Reader',
		'action'     => 'index',
	]);
