<?php
declare(strict_types=1);

use DR\Core\App;

/*
 * Route table. Guards: public | guest | pending | auth | client | staff | lead | admin | master
 * Every POST route is CSRF-checked by the dispatcher.
 */

// ---------------------------------------------------------------- install (only reachable before installation)
App::route('GET', '/install', 'InstallController@form', 'public');
App::route('POST', '/install', 'InstallController@run', 'public');

// ---------------------------------------------------------------- authentication
App::route('GET', '/login', 'AuthController@loginForm', 'guest');
App::route('POST', '/login', 'AuthController@login', 'guest');
App::route('GET', '/login/verify', 'AuthController@verifyForm', 'pending');
App::route('POST', '/login/verify', 'AuthController@verify', 'pending');
App::route('GET', '/login/setup', 'AuthController@setupForm', 'pending');
App::route('POST', '/login/setup', 'AuthController@setup', 'pending');
App::route('POST', '/login/cancel', 'AuthController@cancel', 'public');
App::route('POST', '/logout', 'AuthController@logout', 'public');
App::route('GET', '/invite/{token}', 'AuthController@inviteForm', 'public');
App::route('POST', '/invite/{token}', 'AuthController@invite', 'public');
App::route('GET', '/forgot', 'AuthController@forgotForm', 'guest');
App::route('POST', '/forgot', 'AuthController@forgot', 'guest');
App::route('GET', '/reset/{token}', 'AuthController@resetForm', 'public');
App::route('POST', '/reset/{token}', 'AuthController@reset', 'public');
App::route('GET', '/sudo', 'AuthController@sudoForm', 'auth');
App::route('POST', '/sudo', 'AuthController@sudo', 'auth');
App::route('GET', '/status', 'StatusController@form', 'public');
App::route('POST', '/status', 'StatusController@request', 'public');
App::route('GET', '/status/{token}', 'StatusController@show', 'public');
App::route('GET', '/cron/{token}', 'CronController@web', 'public');

// ---------------------------------------------------------------- shared (every signed-in role)
App::route('GET', '/account', 'AccountController@show', 'auth');
App::route('POST', '/account/profile', 'AccountController@profile', 'auth');
App::route('POST', '/account/password', 'AccountController@password', 'auth');
App::route('POST', '/account/recovery', 'AccountController@regenerateCodes', 'auth');
App::route('GET', '/account/recovery-codes', 'AccountController@showCodes', 'auth');
App::route('POST', '/account/sessions/revoke', 'AccountController@revokeSessions', 'auth');
App::route('POST', '/account/2fa/reset', 'AccountController@resetOwn2fa', 'auth');
App::route('GET', '/notifications', 'NotificationController@index', 'auth');
App::route('POST', '/notifications/read', 'NotificationController@readAll', 'auth');
App::route('GET', '/notifications/{id}', 'NotificationController@open', 'auth');
App::route('GET', '/search', 'SearchController@query', 'auth');

// documents and uploads (row-level checks inside)
App::route('POST', '/uploads/start', 'DocumentController@start', 'auth');
App::route('POST', '/uploads/{id}/chunk', 'DocumentController@chunk', 'auth');
App::route('POST', '/uploads/{id}/finish', 'DocumentController@finish', 'auth');
App::route('GET', '/documents/{id}/download', 'DocumentController@download', 'auth');
App::route('GET', '/documents/{id}/view', 'DocumentController@inline', 'auth');
App::route('POST', '/documents/{id}/update', 'DocumentController@update', 'staff');
App::route('POST', '/documents/{id}/delete', 'DocumentController@delete', 'auth');
App::route('POST', '/folders', 'DocumentController@createFolder', 'staff');

// ---------------------------------------------------------------- staff workspace
App::route('GET', '/', 'DashboardController@index', 'staff');

App::route('GET', '/leads', 'LeadController@index', 'staff');
App::route('GET', '/leads/{id}', 'LeadController@show', 'staff');
App::route('POST', '/leads/{id}/claim', 'LeadController@claim', 'lead');
App::route('POST', '/leads/{id}/screening', 'LeadController@screening', 'lead');
App::route('POST', '/leads/{id}/accept', 'LeadController@accept', 'lead');
App::route('POST', '/leads/{id}/decline', 'LeadController@decline', 'lead');

App::route('GET', '/clients', 'ClientController@index', 'staff');
App::route('GET', '/clients/new', 'ClientController@createForm', 'lead');
App::route('POST', '/clients', 'ClientController@create', 'lead');
App::route('GET', '/clients/{id}', 'ClientController@show', 'staff');
App::route('POST', '/clients/{id}', 'ClientController@update', 'lead');
App::route('POST', '/clients/{id}/users', 'ClientController@inviteUser', 'lead');
App::route('POST', '/clients/{id}/users/{uid}/resend', 'ClientController@resendInvite', 'lead');
App::route('POST', '/clients/{id}/contacts', 'ClientController@addContact', 'staff');
App::route('POST', '/clients/{id}/contacts/{cid}/delete', 'ClientController@deleteContact', 'lead');
App::route('POST', '/clients/{id}/erase', 'ClientController@erase', 'master');

App::route('GET', '/cases', 'CaseController@index', 'staff');
App::route('GET', '/cases/new', 'CaseController@createForm', 'lead');
App::route('POST', '/cases', 'CaseController@create', 'lead');
App::route('GET', '/cases/{id}', 'CaseController@show', 'staff');
App::route('POST', '/cases/{id}', 'CaseController@update', 'staff');
App::route('POST', '/cases/{id}/team', 'CaseController@team', 'lead');
App::route('POST', '/cases/{id}/messages', 'CaseController@message', 'staff');
App::route('POST', '/cases/{id}/targets', 'TrackerController@saveTarget', 'staff');
App::route('POST', '/cases/{id}/targets/{tid}/delete', 'TrackerController@deleteTarget', 'staff');
App::route('POST', '/cases/{id}/funds', 'TrackerController@saveFunds', 'staff');
App::route('POST', '/cases/{id}/funds/{fid}/delete', 'TrackerController@deleteFunds', 'staff');
App::route('GET', '/cases/{id}/targets.csv', 'TrackerController@exportTargets', 'staff');

App::route('GET', '/tasks', 'TaskController@index', 'staff');
App::route('POST', '/tasks', 'TaskController@save', 'staff');
App::route('POST', '/tasks/{id}/status', 'TaskController@status', 'staff');
App::route('POST', '/tasks/{id}/delete', 'TaskController@delete', 'staff');

App::route('GET', '/calendar', 'CalendarController@index', 'staff');
App::route('POST', '/deadlines', 'CalendarController@save', 'staff');
App::route('POST', '/deadlines/{id}/status', 'CalendarController@status', 'staff');
App::route('POST', '/deadlines/{id}/delete', 'CalendarController@delete', 'staff');

App::route('GET', '/invoices', 'InvoiceController@index', 'staff');
App::route('GET', '/invoices/new', 'InvoiceController@createForm', 'lead');
App::route('POST', '/invoices', 'InvoiceController@create', 'lead');
App::route('GET', '/invoices/{id}', 'InvoiceController@show', 'staff');
App::route('POST', '/invoices/{id}', 'InvoiceController@update', 'lead');
App::route('POST', '/invoices/{id}/send', 'InvoiceController@send', 'lead');
App::route('POST', '/invoices/{id}/cancel', 'InvoiceController@cancel', 'admin');
App::route('POST', '/invoices/{id}/payments', 'InvoiceController@payment', 'admin');
App::route('POST', '/invoices/{id}/payments/{pid}/delete', 'InvoiceController@deletePayment', 'admin');
App::route('GET', '/invoices/{id}/print', 'InvoiceController@printView', 'auth');

App::route('GET', '/reports', 'ReportController@index', 'lead');
App::route('GET', '/reports/export', 'ReportController@export', 'admin');

// ---------------------------------------------------------------- administration
App::route('GET', '/admin/users', 'UserController@index', 'admin');
App::route('POST', '/admin/users', 'UserController@create', 'admin');
App::route('GET', '/admin/users/{id}', 'UserController@show', 'admin');
App::route('POST', '/admin/users/{id}', 'UserController@update', 'admin');
App::route('POST', '/admin/users/{id}/reset-2fa', 'UserController@reset2fa', 'admin');
App::route('POST', '/admin/users/{id}/resend', 'UserController@resend', 'admin');
App::route('POST', '/admin/users/{id}/sessions', 'UserController@revokeSessions', 'admin');
App::route('GET', '/admin/catalog', 'CatalogController@index', 'admin');
App::route('POST', '/admin/catalog', 'CatalogController@save', 'admin');
App::route('GET', '/admin/audit', 'AuditController@index', 'admin');
App::route('GET', '/admin/audit.csv', 'AuditController@export', 'admin');
App::route('GET', '/admin/settings', 'SettingsController@index', 'master');
App::route('POST', '/admin/settings', 'SettingsController@save', 'master');
App::route('POST', '/admin/settings/test-mail', 'SettingsController@testMail', 'master');
App::route('GET', '/admin/security', 'SecurityController@index', 'master');
App::route('POST', '/admin/security/rotate', 'SecurityController@rotate', 'master');
App::route('POST', '/admin/security/backup', 'SecurityController@backup', 'master');
App::route('GET', '/admin/security/backup/{name}', 'SecurityController@downloadBackup', 'master');
App::route('POST', '/admin/security/retention', 'SecurityController@retention', 'master');
App::route('POST', '/admin/security/maintenance', 'SecurityController@maintenance', 'master');

// ---------------------------------------------------------------- client and adviser portal
App::route('GET', '/client', 'PortalController@home', 'client');
App::route('GET', '/client/cases/{id}', 'PortalController@case', 'client');
App::route('POST', '/client/cases/{id}/messages', 'PortalController@message', 'client');
App::route('POST', '/client/cases/{id}/nda', 'PortalController@signNda', 'client');
App::route('GET', '/client/documents', 'PortalController@documents', 'client');
App::route('GET', '/client/invoices', 'PortalController@invoices', 'client');
App::route('GET', '/client/export', 'PortalController@export', 'client');

// Website CMS (posts, verified testimonials, redirects). Literal paths before {id}.
App::route('GET', '/cms/posts', 'CmsController@posts', 'lead');
App::route('GET', '/cms/posts/new', 'CmsController@postForm', 'lead');
App::route('POST', '/cms/posts', 'CmsController@savePost', 'lead');
App::route('GET', '/cms/posts/{id}', 'CmsController@postForm', 'lead');
App::route('POST', '/cms/posts/{id}', 'CmsController@savePost', 'lead');
App::route('POST', '/cms/posts/{id}/delete', 'CmsController@deletePost', 'admin');
App::route('GET', '/cms/testimonials', 'CmsController@testimonials', 'lead');
App::route('POST', '/cms/testimonials', 'CmsController@saveTestimonial', 'lead');
App::route('POST', '/cms/testimonials/{id}/approve', 'CmsController@approveTestimonial', 'admin');
App::route('POST', '/cms/testimonials/{id}/hide', 'CmsController@hideTestimonial', 'admin');
App::route('POST', '/cms/testimonials/{id}/delete', 'CmsController@deleteTestimonial', 'admin');
App::route('GET', '/cms/redirects', 'CmsController@redirects', 'admin');
App::route('POST', '/cms/redirects', 'CmsController@saveRedirect', 'admin');
App::route('POST', '/cms/redirects/{id}/delete', 'CmsController@deleteRedirect', 'admin');
