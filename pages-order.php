<?php
/**
Plugin Name: Pages Order
Plugin Tag: plugin, 
Description: <p>With this plugin, you may re-order the order of the pages and the hierarchical order of the pages.</p><p>Moreover, you may add this hierarchy into your page to ease the navigation of viewers into your website</p>
Version: 1.0.1
Framework: SL_Framework
Author: SedLex
Author Email: sedlex@sedlex.fr
Framework Email: sedlex@sedlex.fr
Author URI: http://www.sedlex.fr/
Plugin URI: http://wordpress.org/extend/plugins/pages-order/
License: GPL3
*/

//Including the framework in order to make the plugin work

require_once('core.php') ; 

/** ====================================================================================================================================================
* This class has to be extended from the pluginSedLex class which is defined in the framework
*/
class pages_order extends pluginSedLex {
	

	/** ====================================================================================================================================================
	* Plugin initialization
	* 
	* @return void
	*/
	static $instance = false;

	protected function _init() {
		global $wpdb ; 

		// Name of the plugin (Please modify)
		$this->pluginName = 'Pages Order' ; 
		
		// The structure of the SQL table if needed (for instance, 'id_post mediumint(9) NOT NULL, short_url TEXT DEFAULT '', UNIQUE KEY id_post (id_post)') 
		$this->tableSQL = "" ; 
		// The name of the SQL table (Do no modify except if you know what you do)
		$this->table_name = $wpdb->prefix . "pluginSL_" . get_class() ; 

		//Initilisation of plugin variables if needed (Please modify)

		//Configuration of callbacks, shortcode, ... (Please modify)
		// For instance, see 
		//	- add_shortcode (http://codex.wordpress.org/Function_Reference/add_shortcode)
		//	- add_action 
		//		- http://codex.wordpress.org/Function_Reference/add_action
		//		- http://codex.wordpress.org/Plugin_API/Action_Reference
		//	- add_filter 
		//		- http://codex.wordpress.org/Function_Reference/add_filter
		//		- http://codex.wordpress.org/Plugin_API/Filter_Reference
		// Be aware that the second argument should be of the form of array($this,"the_function")
		// For instance add_action( "the_content",  array($this,"modify_content")) : this function will call the function 'modify_content' when the content of a post is displayed
		
		// add_action( "the_content",  array($this,"modify_content")) ; 
		add_action( 'wp_ajax_savePageHierarchy', array($this,"save_tree") );
		add_shortcode( "page_tree", array($this,"page_tree") );

		// Important variables initialisation (Do not modify)
		$this->path = __FILE__ ; 
		$this->pluginID = get_class() ; 
		
		// activation and deactivation functions (Do not modify)
		register_activation_hook(__FILE__, array($this,'install'));
		register_deactivation_hook(__FILE__, array($this,'deactivate'));
		register_uninstall_hook(__FILE__, array($this,'uninstall_removedata'));
	}

	/**====================================================================================================================================================
	* Function called when the plugin is activated
	* For instance, you can do stuff regarding the update of the format of the database if needed
	* If you do not need this function, you may delete it.
	*
	* @return void
	*/
	
	public function _update() {
		SL_Debug::log(get_class(), "Update the plugin." , 4) ; 
	}
	
	/**====================================================================================================================================================
	* Function called to return a number of notification of this plugin
	* This number will be displayed in the admin menu
	*
	* @return int the number of notifications available
	*/
	 
	public function _notify() {
		return 0 ; 
	}
	
	/** ====================================================================================================================================================
	* Init javascript for the admin side
	* If you want to load a script, please type :
	* 	<code>wp_enqueue_script( 'jsapi', 'https://www.google.com/jsapi');</code> or 
	*	<code>wp_enqueue_script('pages_order_script', plugins_url('/script.js', __FILE__));</code>
	*
	* @return void
	*/
	
	function _admin_js_load() {	
		return ; 
	}
	
	/**====================================================================================================================================================
	* Function to instantiate the class and make it a singleton
	* This function is not supposed to be modified or called (the only call is declared at the end of this file)
	*
	* @return void
	*/
	
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	/** ====================================================================================================================================================
	* Define the default option values of the plugin
	* This function is called when the $this->get_param function do not find any value fo the given option
	* Please note that the default return value will define the type of input form: if the default return value is a: 
	* 	- string, the input form will be an input text
	*	- integer, the input form will be an input text accepting only integer
	*	- string beggining with a '*', the input form will be a textarea
	* 	- boolean, the input form will be a checkbox 
	* 
	* @param string $option the name of the option
	* @return variant of the option
	*/
	public function get_default_option($option) {
		switch ($option) {
			// Alternative default return values (Please modify)
			case 'other_style' 		: return "font-weight:normal;color:#DDDDDD;" 		; break ; 
			case 'parent_style' 		: return "font-weight:bold;color:#333333;" 		; break ; 
			case 'current_style' 		: return "font-weight:bold;color:#DD3333;" 		; break ; 
			case 'child_style' 		: return "font-weight:normal;color:#333333;" 		; break ; 
		}
		return null ;
	}

	/** ====================================================================================================================================================
	* The admin configuration page
	* This function will be called when you select the plugin in the admin backend 
	*
	* @return void
	*/
	
	public function configuration_page() {
		global $wpdb;
		global $blog_id ; 
		
		SL_Debug::log(get_class(), "Print the configuration page." , 4) ; 

		?>
		<div class="wrap">
			<div id="icon-themes" class="icon32"><br></div>
			<h2><?php echo $this->pluginName ?></h2>
		</div>
		<div style="padding:20px;">			
			<?php
			//===============================================================================================
			// After this comment, you may modify whatever you want
			?>
			<p><?php echo __("With this plugin you may order your pages into hierarchical tree and display the tree in them.", $this->pluginID) ;?></p>
			<p><?php echo sprintf(__("To display the tree please add %s in your page.", $this->pluginID), "<code>[page_tree]</code>") ;?></p>
			<?php
			
			// We check rights
			$this->check_folder_rights( array(array(WP_CONTENT_DIR."/sedlex/test/", "rwx")) ) ;
			
			$tabs = new adminTabs() ; 
			
			ob_start() ; 
				echo "<p>".__("In this tab, you could re-order the page hierarchy by 'drag-and-dropping' page entries.", $this->pluginID)."</p>" ; 
			
				$args = array(
					'sort_order' => 'ASC',
					'sort_column' => 'menu_order,post_title',
					'parent' => 0,
					'child_of' => 0,
					'offset' => 0,
					'post_type' => 'page',
					'post_status' => 'publish,draft,pending,future'
				);
				
				treeList::render($this->create_hierarchy_pages(get_pages($args)), true, 'savePageHierarchy', 'page_hiera');
						
			$tabs->add_tab(__('Order Pages',  $this->pluginID), ob_get_clean()) ; 	

			ob_start() ; 
				$params = new parametersSedLex($this, "tab-parameters") ; 
				
				$params->add_title(__("Tree displayed in pages", $this->pluginID)) ; 
				$params->add_param('current_style', __("Set the style of current page in tree:", $this->pluginID)) ; 
				$params->add_param('parent_style', __("Set the style of parent pages in tree:", $this->pluginID)) ; 
				$params->add_param('child_style', __("Set the style of child pages in tree:", $this->pluginID)) ; 
				$params->add_param('other_style', __("Set the style of other pages in tree:", $this->pluginID)) ; 
				
				$params->add_title(__("Advanced options", $this->pluginID)) ; 
				$params->add_param('css_tree', __("CSS for the tree", $this->pluginID)) ; 
				$css_tree = "<br><code>xxx</code>" ; 
				$params->add_comment(sprintf(__("Default CSS for the tree: %s", $this->pluginID),$css_tree)) ; 
				
				$params->flush() ; 
				
			$tabs->add_tab(__('Parameters',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_param.png") ; 	
			
			$frmk = new coreSLframework() ;  
			if (((is_multisite())&&($blog_id == 1))||(!is_multisite())||($frmk->get_param('global_allow_translation_by_blogs'))) {
				ob_start() ; 
					$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
					$trans = new translationSL($this->pluginID, $plugin) ; 
					$trans->enable_translation() ; 
				$tabs->add_tab(__('Manage translations',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_trad.png") ; 	
			}

			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new feedbackSL($plugin, $this->pluginID) ; 
				$trans->enable_feedback() ; 
			$tabs->add_tab(__('Give feedback',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_mail.png") ; 	
			
			ob_start() ; 
				// A liste of plugin slug to be excluded
				$exlude = array('wp-pirate-search') ; 
				// Replace sedLex by your own author name
				$trans = new otherPlugins("sedLex", $exlude) ; 
				$trans->list_plugins() ; 
			$tabs->add_tab(__('Other plugins',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_plug.png") ; 	
			
			echo $tabs->flush() ; 
			
			
			// Before this comment, you may modify whatever you want
			//===============================================================================================
			?>
			<?php echo $this->signature ; ?>
		</div>
		<?php
	}

	
	/** ====================================================================================================================================================
	* Display the pages as a list
	*
	* @return array
	*/

	function create_hierarchy_pages($array, $id_to_show=0, $display=true) {
			
		$result = array() ; 
		
		foreach ( $array as $a ) {
			if ($id_to_show==0){
				$text = $this->get_text($a) ; 
			} else {
				if ($a->ID == $id_to_show) {
					$text = $this->get_text($a, $this->get_param('current_style')) ; 
				} else if ($this->is_child(get_post($a->ID), $id_to_show)) {
					$text = $this->get_text($a, $this->get_param('parent_style')) ; 
				} else if ($this->is_parent(get_post($a->ID), $id_to_show)) {
					$text = $this->get_text($a, $this->get_param('child_style')) ; 
				} else {
					$text = $this->get_text($a, $this->get_param('other_style')) ; 
				}
			}

			// We recurse !
			$args = array(
				'sort_order' => 'ASC',
				'sort_column' => 'menu_order,post_title',
				'parent' => $a->ID,
				'child_of' => $a->ID,
				'offset' => 0,
				'post_type' => 'page',
			);
			// Check if the user may edit page
			if ( current_user_can('edit_published_pages') ) { 
				$args['post_status'] = 'publish,draft,pending,future' ; 
			} else {
				$args['post_status'] = 'publish' ; 
			}
			
			$child = get_pages($args) ; 
			if (count($child)!=0) {
				if (($id_to_show==0)||($display==false)) {
					$r = array($text,'page_'. $a->ID, $this->create_hierarchy_pages($child, $id_to_show, $display), $display) ; 
				} else {
					if (($this->is_parent(get_post($a->ID), $id_to_show)) || ($this->is_child(get_post($a->ID), $id_to_show)) ){
						$r = array($text,'page_'. $a->ID, $this->create_hierarchy_pages($child, $id_to_show, true), true) ; 
					} else {
						$r = array($text,'page_'. $a->ID, $this->create_hierarchy_pages($child, $id_to_show, false), false) ; 
					}
				}
			} else {
				$r = array($text,'page_'. $a->ID, null, $display) ; 
			}
			$result[] = $r ; 
		}
		return $result ; 
	}
	
	/** ====================================================================================================================================================
	* Get Text for the list given a post
	*
	* @return string	*/

	function get_text($a, $style="") {
		$text = "" ; 
		if ( current_user_can('edit_published_pages') ) { 
			if ($a->post_status=="publish") {
				$text .= '<span class="page_status page_published">'.__('Published', $this->pluginID).'</span>' ; 
			}
			if ($a->post_status=="draft") {
				$text .= '<span class="page_status page_draft">'.__('Draft', $this->pluginID).'</span>' ; 
			}
			if ($a->post_status=="pending") {
				$text .= '<span class="page_status page_pending">'.__('Pending', $this->pluginID).'</span>' ; 
			}
			if ($a->post_status=="future") {
				$text .= '<span class="page_status page_future">'.__('Future', $this->pluginID).'</span>' ; 
			}
		}
		
		$text .= "<img src='".WP_PLUGIN_URL."/".str_replace(basename(__FILE__),"",plugin_basename( __FILE__))."core/img/default.png'/>" ; 
		
		$text .= '<span style="'.$style.'">'.$a->post_title.'</span>' ;
	 
		// Print actions
		$text .= ' <span class="page_actions">(' ;
		$text .= '<a class="page_action_editorview" href="'.get_permalink( $a->ID ).'">'.__( 'View' , $this->pluginID).'</a>';

		// has capabilities to edit this page?
		if ( $edit = get_edit_post_link( $a->ID ) )
			$text .= ' | <a class="page_action_editorview" href="'.$edit.'">'.__( 'Edit' , $this->pluginID).'</a>';
		// has capabilities to delete this page?
		if ( $delete = get_delete_post_link( $a->ID ) )
			$text .= ' | <a class="page_action_delete" href="'.$delete.'">'.__( 'Trash', $this->pluginID).'</a>';

		$text .= ')</span>' ;
		return $text ; 
	}
	
	/** ====================================================================================================================================================
	* Callback for saving the hierarchy of pages
	*
	* @return void
	*/
	
	function save_tree() {
		$array = $_POST['result'] ; 
		$this->save_tree_recurse($array, 0) ; 
		echo "OK" ; 
		die() ; 
	}
	
	function save_tree_recurse($array, $parent_id) {
		$order = 1 ; 
		foreach ($array as $a) {
			$id_page = str_replace('page_', '', $a[0]) ; 
			$old_page = get_page($id_page) ; 
			if (($old_page->post_parent != $parent_id)||($old_page->menu_order!=$order)) {
  				$my_post = array();
 				$my_post['ID'] = $id_page;
  				$my_post['post_parent'] = $parent_id;
  				$my_post['menu_order'] = $order;
				// Update the post into the database
  				if (wp_update_post( $my_post )==0) {
  					echo "Error when saving ".$id_page." as child of ".$parent_id." with menu order ".$order."\n" ;
  				}
			}
			$child = $a[1] ; 
			if (is_array($child)) {
				$this->save_tree_recurse($child, $id_page) ; 
			}
			$order++ ; 
		}
	}
	
	/** ====================================================================================================================================================
	* Check whether the post_id is a parent of the post
	* 
	* @return boolean true if the post_id os a parent page of post
	*/	
	
	function is_parent( $post, $post_id ) {
		if (is_page() && ($post->ID == $post_id)) {
			return true;
		} else if ($post->post_parent == 0) {
			return false;
		} else {
			return $this->is_parent( get_post($post->post_parent), $post_id );
		}
	}
	
	/** ====================================================================================================================================================
	* Get the root of the page hierachy
	* 
	* @return the ID of the root page
	*/	
	
	function get_root( $post_id ) {
		$post = get_post($post_id) ; 
		$parent = $post->post_parent ; 

		if ($parent == 0) {
			return $post->ID;
		} else {
			return $this->get_root($parent);
		}
	}
	
	/** ====================================================================================================================================================
	* Check whether the post_id is a child of the post
	* 
	* @return boolean true if the post_id os a child page of post
	*/	
	
	function is_child($post, $post_id) { 
		if ($post->ID==$post_id) {
		       return true;
		} else { 
			$return = false ; 
			
			// We recurse !
			$args = array(
				'sort_order' => 'ASC',
				'sort_column' => 'menu_order,post_title',
				'parent' => $post->ID,
				'child_of' => $post->ID,
				'offset' => 0,
				'post_type' => 'page',
			);
			
			// Check if the user may edit page
			if ( current_user_can('edit_published_pages') ) { 
				$args['post_status'] = 'publish,draft,pending,future' ; 
			} else {
				$args['post_status'] = 'publish' ; 
			}
			
			$child = get_pages($args) ; 
			if (count($child)!=0) {
				foreach ( $child as $c ) {
					$return = ( $return || $this->is_child($c, $post_id) ); 
				}
			} 
		       return $return; 
		}
	}
	/** ====================================================================================================================================================
	* Call when meet the shortcode "[page_tree]" in an post/page
	* 
	* @return string the replacement string
	*/	
	
	function page_tree($attribs) {	
		global $post ; 
		// We check that we are in a page and not in a post
		if (!is_page())
			return "" ; 
		ob_start() ;
			$args = array(	
				'sort_order' => 'ASC',
				'sort_column' => 'menu_order,post_title',
				'parent' => $this->get_root($post->ID),
				'child_of' => $this->get_root($post->ID),
				'offset' => 0,
				'post_type' => 'page'
			);
			// Check if the user may edit page
			if ( current_user_can('edit_published_pages') ) { 
				$args['post_status'] = 'publish,draft,pending,future' ; 
			} else {
				$args['post_status'] = 'publish' ; 
			}
			
			$children = $this->create_hierarchy_pages(get_pages($args), $post->ID) ; 
			
			$id_to_show = $post->ID ; 
			$a = get_post($this->get_root($post->ID)) ; 
			
			if ($a->ID == $id_to_show) {
				$text = $this->get_text($a, $this->get_param('current_style')) ; 
			} else if ($this->is_child(get_post($a->ID), $id_to_show)) {
				$text = $this->get_text($a, $this->get_param('parent_style')) ; 
			} else if ($this->is_parent(get_post($a->ID), $id_to_show)) {
				$text = $this->get_text($a, $this->get_param('child_style')) ; 
			} else {
				$text = $this->get_text($a, $this->get_param('other_style')) ; 
			}

			$to_show = array(array($text,'page_'. $post->ID, $children, true)) ; 
			
			treeList::render($to_show, true, null, 'page_hiera');
		$out .= ob_get_clean() ; 
		return "<div style='margin:10px;padding:10px;'>".$out."</div>" ;
	}
}

$pages_order = pages_order::getInstance();

?>