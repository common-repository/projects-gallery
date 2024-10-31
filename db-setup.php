<?php

global $pg_db_version;
$pg_db_version = '1.0';

function project_gallery_install() {
	global $wpdb;
	global $pg_db_version;

	$table_name = $wpdb->prefix ."projects";
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id int(11) NOT NULL AUTO_INCREMENT,
        title varchar(200) NOT NULL,
        image varchar(500) NOT NULL,
        enabled tinyint(4) DEFAULT 0 NOT NULL,
        description varchar(500) NOT NULL,
        tech varchar(500) NOT NULL,
        link varchar(200) NOT NULL,
        gitlink varchar(300) NOT NULL,
        year  varchar(4) NOT NULL,
        show_order int(11) NOT NULL,
        PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'pg_db_version', $pg_db_version );
}