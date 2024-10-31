<?php
/*
  Plugin Name: Projects Gallery
  Author: Nazanin Hesamzadeh
  Author URI: https://profiles.wordpress.org/nazaninhesamzadeh
  Description: This plugin lists all the projects, creates a gallery of projects.
  version: 1.0
  License: GPLv2
*/
/*
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if( class_exists("projectGallery") ){
    require_once "db-setup.php";
    register_activation_hook( __FILE__, 'project_gallery_install' );
    new projectGallery();
}
class projectGallery{
    function __construct(){ 
      global $orderby;
      global $order;
       if(isset($_REQUEST['orderby'])){
        $orderby = htmlspecialchars(strtolower($_REQUEST["orderby"]));
      }
      if(isset($_GET['order'])) {  
        $order = htmlspecialchars(strtolower($_GET["order"])); 
        
      }
      add_action('wp_enqueue_scripts', array($this,'cb_setting_up_scripts'));
      add_shortcode("mw_pgallery", array($this, 'list'));
      add_action( 'admin_menu', array($this, 'mw_admin_menu') );
     }
    function cb_setting_up_scripts() { 
     wp_enqueue_style( 'pg-bootstrap', plugin_dir_url( __FILE__) . 'public/bootstrap.min.css', false, NULL, 'all' );
     wp_enqueue_style( 'pg-materialize', plugin_dir_url( __FILE__) . 'public/materialize.css', false, NULL, 'all' );
     wp_enqueue_style( 'pg-materialize-icon', plugin_dir_url( __FILE__) . 'public/icon.css', false, NULL, 'all' );
     wp_enqueue_script( 'pg-materializejs', plugin_dir_url( __FILE__) . 'public/materialize.min.js', array( 'jquery' ) );
    }
     function list($atts){
       global $orderby; 
       global $order;
         $att = shortcode_atts(array(
          'front' => 'true',
          'order' => $order,
          'orderby' => $orderby
        ), $atts );
        switch($att['orderby']){
          case 'order':
            $orderby = 'show_order';
          break;
          case 'date':
            $orderby = 'year';
          break;
          case 'title':
            $orderby = 'title';
          break;
          default:
            $orderby = 'id';
        }
        if($att['order'] === 'asc') $order = 'asc';
        else $order= 'desc';

        global $wpdb; 
        $lists = '';
        if($att['front'] === 'true'){ 
          $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM ". $wpdb->prefix ."projects WHERE enabled = %d ORDER BY ". $orderby . " " . $order , 0 ));
            $lists = '<div class="row">';
            foreach($results as $item){
            $lists .= $this->format_item($item);
          }
          $lists .= '</div>'; 
        }else{
          $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM ". $wpdb->prefix ."projects  ORDER BY ". $orderby . " " . $order , 0));
          foreach($results as $item){
            $lists .= $this->format_admin_list($item);
          }
        }
        return '<div>'. $lists .'</div>';
     }
     
     function projects_admin_master(){
       if(user_can(wp_get_current_user(),'manage_options')){
         $action = isset($_GET['action']) ? $_GET['action'] :'';
         
         switch($action){
           case 'edit':
             $id = $this->sanitize_id($_GET['id']);
             echo $this->admin_master_edit($id);
            break;
           //after submitting new form
           case 'new':
           case 'editedproject':
           case 'archive':
            if($_REQUEST["page"]==='projects'){
              $res = $this->modify_project();
              //alert project updated
              echo $this->admin_master_list();
            }else{ 
              die('Wrong referrer!');
            }
            break;
           default:
              echo $this->admin_master_list();
         }
       }else{
         die('Authorization Error!');
       }
     }

     function modify_project(){
      $item = $_REQUEST;
      global $wpdb;
      if($item['action'] === 'archive'){ 
        $id = (int) $this->sanitize_id($_REQUEST['id']);
        return $wpdb->update($wpdb->prefix ."projects", array("enabled"=>1) , array("id" => $id));
      }
      if(wp_verify_nonce( $item['_wpnonce'], "modify-pr")){
        $modify_data = array(
          "title" => stripslashes(sanitize_text_field( $_POST['project_title'] )),
          "tech" => sanitize_textarea_field( $_POST['project_tech'] ),
          "link" => sanitize_text_field( $_POST['live_link'] ),
          "gitlink" => sanitize_text_field( $_POST['git_link'] ),
          "description" => stripslashes(sanitize_textarea_field( $_POST['project_desc'] )),
          "image" => sanitize_text_field( $_POST['project_image'] ),
          "enabled" => sanitize_text_field( $_POST['project_status'] ),
          "show_order" => sanitize_text_field( $_POST['show_order'] ),
          "year" => sanitize_text_field( $_POST['project_year'] ),
        );
        
         if($_FILES['project_image_file']['name'] != ''){
          $uploadedfile = $_FILES['project_image_file'];
          $upload_overrides = array( 'test_form' => false );
          $movefile = wp_handle_upload( $uploadedfile, $upload_overrides );
          $imageurl = "";
          if($movefile && ! isset( $movefile['error'])){
            $modify_data["image"] = $movefile['url'];
          } else {
             die($movefile['error']);
          }
        }
        if($item['action'] === 'editedproject'){
          $id = (int) $this->sanitize_id($_REQUEST['project_id']);
          return $wpdb->update($wpdb->prefix ."projects", $modify_data , array("id" => $id));
        }else if($item['action'] === 'new'){
          $format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');
          return $wpdb->insert($wpdb->prefix ."projects",$modify_data,$format);
        }
    }
     }
     function admin_master_edit($id){
       global $wpdb;
        $results = $wpdb->get_results(
          $wpdb->prepare("SELECT * FROM ". $wpdb->prefix ."projects WHERE id=%d " , $id));
          if(is_array($results) && count($results)){
            $this->format_admin_project_modify($results[0]);
          }else{
            die("no record found!");
          }
     }
     function admin_master_list(){ 
       global $order;
       global $orderby;
      $class_sortname = ($order === "asc")? "desc":"asc";
      $pageLayout = '<div class="wrap">
      <h1 class="wp-heading-inline">Projects Gallery</h1>
       <a href="admin.php?page=project-new" class="page-title-action">Add New</a>
      <hr class="wp-header-end">
      <h2 class="screen-reader-text">Filter Projects list</h2>
      <form id="posts-filter" method="get">
        '.wp_nonce_field( "search-pr" , "_wpnonce",  false, false ).'
       <br class="clear">
          <h2 class="screen-reader-text">Project list</h2><table class="wp-list-table widefat fixed striped pages">
        <thead>
        <tr>
          <td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox"></td>
          <th scope="col" id="title" class="manage-column column-title column-primary '. $order . (($orderby === "title")?" sorted":" sortable"). '">
            <a href="admin.php?page=projects&amp;orderby=title&amp;order='. $class_sortname .'">
              <span>Title</span>
              <span class="sorting-indicator"></span>
            </a>
          </th>
          <th scope="col" id="author" class="manage-column column-author '. $order . (($orderby === "order")?" sorted":" sortable"). '">
            <a href="admin.php?page=projects&amp;orderby=order&amp;order='. $class_sortname .'">
            <span>Order</span>
            <span class="sorting-indicator"></span>
            </a>
          </th>
          <th scope="col" id="comments" class="manage-column column-comments num">
            <span>Screens</span>
          </th>
          <th scope="col" id="date" class="manage-column column-date">
            <span>Date</span>
          </th>
       </tr>
        </thead>
        <tbody id="the-list"> '. $this->list(array('front' => false)) .'</tbody>
        <tfoot>
        <tr>
          <td class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-2">Select All</label><input id="cb-select-all-2" type="checkbox"></td>
          <th scope="col" id="title" class="manage-column column-title column-primary '. $order . (($orderby === "title")?" sorted":" sortable"). '">
          <a href="admin.php?page=projects&amp;orderby=title&amp;order='. $class_sortname .'">
            <span>Title</span>
            <span class="sorting-indicator"></span>
          </a>
        </th>
        <th scope="col" id="author" class="manage-column column-author '. $order . (($orderby === "order")?" sorted":" sortable"). '">
          <a href="admin.php?page=projects&amp;orderby=order&amp;order='. $class_sortname .'">
          <span>Order</span>
          <span class="sorting-indicator"></span>
          </a>
        </th>
        <th scope="col" id="comments" class="manage-column column-comments num">
          <span>Screens</span>
        </th>
        <th scope="col" id="date" class="manage-column column-date">
          <span>Date</span>
        </th>
      </tfoot>
      </table>
      </form>
      </div>';
      return $pageLayout;
     }
     function projects_admin_new(){
       if(user_can(wp_get_current_user(),'manage_options')){
        $this->format_admin_project_modify();
      }
     }
     function mw_admin_menu(){
      add_menu_page(
        __( 'Projects Gallery', 'textdomain' ),
        'Projects Gallery',
        'manage_options', //'moderate_comments', //manage_options
        'projects',
        array($this , 'projects_admin_master'),
        'dashicons-clipboard',
        160 );
      add_submenu_page(
        'projects',
        'Add New',
        'Add New',
        'manage_options',
       'project-new',
        array($this , 'projects_admin_new')
      );
     }
     private function format_admin_project_modify($item = 0){
       $pageLayout = "";
       $action_rest = $action="editedproject";
       $page_title = "Edit Project";
        if($item===0){
          $item = (object) array(
            "id" => 0,
            "title" => "",
            "image"=>"",
            "enabled" => "0",
            "description" => "",
            "tech" => "",
            "link" => "",
            "gitlink"=>"",
            "year"=>"",
            "show_order" => 0
            );
            $action_rest = $action="new";
            $page_title = "New Project";
        }else{
          $action_rest = $action . "&amp;id=" . $item->id;
        }
        $pageLayout .=  '<form name="post" action="admin.php?page=projects&amp;action='. $action_rest.'" method="post" id="post" enctype="multipart/form-data">
          '.wp_nonce_field( "modify-pr" , "_wpnonce",  false, false ).'
          <div class="wrap">
          <h1>'.$page_title.'</h1>
          <div id="poststuff">
          <input type="hidden" name="action" value="'. $action .'">
          <input type="hidden" name="project_id" value="'.$item->id.'">
          <div id="post-body" class="metabox-holder columns-2">
          <div id="post-body-content" class="edit-form-section edit-comment-section">
          <div id="namediv" class="stuffbox">
          <div class="inside">
            <h2 class="edit-comment-author">Project</h2>
            <fieldset>
              <legend class="screen-reader-text">Project Item</legend>
              <table class="form-table editcomment" role="presentation">
              <tbody>
              <tr>
                <td class="first"><label for="name">Title</label></td>
                <td><input type="text" name="project_title" size="30" value="'.$item->title.'" id="name"></td>
              </tr>
              <tr>
                <td class="first"><label for="project_tech">Technology</label></td>
                <td>
                  <input type="text" id="project_tech" name="project_tech" size="300" class="code" value="'. $item->tech .'">
                </td>
              </tr>
              <tr>
              <td class="first"><label for="live_link">Live URI</label></td>
              <td>
                <input type="text" name="live_link" size="30" value="'. $item->link .'" id="live_link">
              </td>
              </tr>
              <tr>
              <td class="first"><label for="git_link">Git URI</label></td>
              <td>
                <input type="text" name="git_link" size="30" value="'. $item->gitlink .'" id="git_link">
              </td>
              </tr>
              <tr>
                <td class="first"><label for="project_desc">Descripion</label></td>
                <td>
                  <textarea id="project_desc" name="project_desc" class="code">'. $item->description .'</textarea>
                </td>
              </tr>
              <tr>
                <td class="first"><label for="project_image">Image</label></td>
                <td>
                  <input type="file" name="project_image_file">
                  <input type="hidden" id="project_image" name="project_image" size="200" class="code" value="'. $item->image .'">
                  <img id="project_image" name="project_image" class="code" src="'. $item->image .' "/>
                </td>
              </tr>
              </tbody>
              </table>
            </fieldset>
          </div>
          </div>
          </div><!-- /post-body-content -->
          <div id="postbox-container-1" class="postbox-container">
          <div id="submitdiv" class="stuffbox">
          <h2>Status</h2>
          <div class="inside">
            <div class="submitbox" id="submitcomment">
              <div id="minor-publishing">
              <div id="misc-publishing-actions">
                <fieldset class="misc-pub-section misc-pub-comment-status" id="comment-status-radio">
                  <legend class="screen-reader-text">Project status</legend>
                  <label><input type="radio" '.(($item->enabled === '0')?'checked="checked"':'').' name="project_status" value="0">Published</label><br>
                  <label><input type="radio" '.(($item->enabled === '1')?'checked="checked"':'').' name="project_status" value="1">Archived</label>
                </fieldset>
                <h2>Year</h2>
                <fieldset class="misc-pub-section misc-pub-comment-status" id="comment-s">
                  <legend><strong>Project Year</strong></legend>
                  <input type="text" id="project_year" name="project_year" size="20" class="code" value="'. $item->year .'">
                </fieldset>
                <h2>Order</h2>
                <fieldset class="misc-pub-section misc-pub-comment-status" id="comment-s">
                  <legend><strong>Project Order</strong></legend>
                  <input type="text" id="show_order" name="show_order" size="20" class="code" value="'. $item->show_order .'">
                </fieldset>
              </div>
              </div>
              <div id="major-publishing-actions">
                <div id="publishing-action">
                <input type="submit" name="save" id="save" class="button button-primary button-large" value="Update">
                </div>
                <div class="clear"></div>
              </div>
            </div><!-- /submitdiv -->
          </div>
          </div><!-- /-body -->
          </div>
          </div>
          </form>';
        echo $pageLayout;
     }
     private function format_admin_list($item){
      $complete_url = wp_nonce_url( "admin.php?page=projects&amp;id=$item->id" ,  'my_nonce' );
      return ('<tr id="post-12" class="iedit author-self level-0 post-12 type-page hentry">
      <th scope="row" class="check-column">
        <label class="screen-reader-text" for="cb-select-12"> Select '. $item->title .'</label>
        <input id="cb-select-12" type="checkbox" name="post[]" value="12">
      </th>
      <td class="title column-title has-row-actions column-primary page-title" data-colname="Title">
        <strong><a class="row-title" href="'. $complete_url .'&amp;action=edit" aria-label="“'. $item->title .'” (Edit)">'. $item->title .'</a></strong>
        <div class="row-actions">
          <span class="edit"><a href="'. $complete_url .'&amp;action=edit" aria-label="Edit “Project”">Edit</a> | </span>
          <span class="archive"><a href="'. $complete_url.'&amp;action=archive" class="submitarchive" aria-label="Move “Project” archived">Archive</a></span>
        </div>
      </td>
      <td class="author column-author" data-colname="Order">'.$item->show_order.'
      </td>
      <td class="comments column-comments" data-colname="Screen">
        <img class="activator" style="width: 70px;" src="' . $item->image .'">
      </td>
      <td class="date column-date" data-colname="Date">' 
      . (($item->enabled === '1' )? 'Archived' :'Published').'<br><abbr title="'.$item->year.'">'.$item->year.'</abbr>
      </td>
    </tr>');
     }
     private function format_item($item){
         $defaultImg = plugin_dir_url( __FILE__) . 'public/main.png';
         $techList = explode(",", $item->tech);
         $techUL = "";
         foreach($techList as $techItem){
          $techUL .= '<li>'.$techItem.'</li>';
         }
         $links = "";
         if($item->link){
          $links .= '<a aria-label="Visit '. $item->title .'" href="'.$item->link.'" style="margin-right: 8px;" target="_blank" data-position="top" data-tooltip="View Online" class="btn-floating waves-effect waves-light blue btn-small"><i class="material-icons">link</i></a>';
         }
         if($item->gitlink){
          $links .= '<a aria-label="Visit the GitHub repo for '. $item->title .'" href="'.$item->gitlink.'" target="_blank" data-position="top" data-tooltip="View Source" class="btn-floating waves-effect waves-light blue btn-small link"><i class="material-icons">storage</i></a>';
         }
         return('
         <div class="col-md-4 col-sx-6">
         <div class="card">
         <div class="card-image waves-effect waves-block waves-light">
           <img class="activator" src="' . (!empty($item->image) ? $item->image: $defaultImg) .'">
         </div>
         <div class="card-content">
           <span class="card-title activator grey-text text-darken-4">'. stripslashes($item->title) .'<i class="material-icons right">more_vert</i></span>
           <p>'. stripslashes($item->description) .'</p>
           <p>' . $links .'</p>
         </div>
         <div class="card-reveal">
           <span class="card-title grey-text text-darken-4">Technologies: <i class="material-icons right">close</i></span>
           <ul>'. $techUL . '</ul>
         </div>
       </div>
       </div>');

    }
     private function sanitize_id($id){
      if (empty($id)) {
        echo('ID is empty');
        exit;
      }
      $sanitized_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
      $sanitized_id = str_replace('-', '', $sanitized_id);
      if (($sanitized_id != $id) || (strlen($sanitized_id) != strlen($id))) {
          echo('Invalid url');
          exit;
      }
      return $sanitized_id;
     }
     
 }

