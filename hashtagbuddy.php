<?php
/*
Plugin Name: HashtagBuddy
Version: 1.0
Description: Buddypress Addon for adding hashtag for activity stream, search hashtag
Author: Subair T C
Author URI:
Plugin URI:
Text Domain: hashtagbuddy
Domain Path: /languages
*/

/*
*	Function to Enqueue required scripts.
*/
function add_hashing_buddy_script() {
    $atwho_js = 'atwho-js';
	wp_register_script( $atwho_js, plugins_url( '/js/jquery.atwho.min.js', __FILE__ ), true );
	wp_enqueue_script( $atwho_js );
	
	wp_register_script( 'caret-js', plugins_url( '/js/jquery.caret.js', __FILE__ ), true );
	wp_enqueue_script( 'caret-js' );
	
	wp_localize_script($atwho_js, 'Ajax', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
	));
}
add_action( 'wp_enqueue_scripts', 'add_hashing_buddy_script' );

/*
*	Function to Enqueue required Styles.
*/
function add_hashing_buddy_style() {

	wp_register_style( 'atwho-css', plugins_url( '/css/jquery.atwho.min.css', __FILE__ ) );
	wp_enqueue_style( 'atwho-css' );
	
	wp_register_style( 'custom-css', plugins_url( '/css/custom.css', __FILE__ ) );
	wp_enqueue_style( 'custom-css' );
}
add_action( 'wp_enqueue_scripts', 'add_hashing_buddy_style' );

function get_tag_taxonamies(){
	// Taxonomy tags.
		$terms = get_terms( array(
			'taxonomy' => 'rarerelated-tag',
			'hide_empty' => false,
		) );
		$json = array();
		foreach ( $terms as $name ) {
			//array_push($json,$name->slug);
			$json[$name->slug] = $name->name;
		}
	return $json;
}


function hb_create_hashtag(){
	$json = get_tag_taxonamies();
	$keys = array_keys($json);
	$values = array_values($json);
	
	$tag_json = wp_json_encode( $json );
	$tag_values =  wp_json_encode( $values );
	$tag_keys =  wp_json_encode( $keys );
	?>
	<script>
$(function(){
	 var tags = <?php echo $tag_values; ?>;
	 var tags_obj = <?php echo $tag_json; ?>;
	 // new post
	  $('#whats-new').atwho({
      at: "#",
      data: tags,
      limit: 200,
      callbacks: {
		beforeInsert : function(value, $li){
			var returnvar = value;
			for (var key in tags_obj ) {
				
				if( value.trim().slice(1) == tags_obj[key] ) {
					returnvar = '#'+key;
				}
			}
			return returnvar;
		},
        afterMatchFailed: function(at, el) {
          // 32 is spacebar
          if (at == '#') {
            tags.push(el.text().trim().slice(1));
            this.model.save(tags);
            this.insert(el.text().trim());
            return false;
          }
        }
      }
    });
	
	// edit post option
	$('#activity_content').atwho({
      at: "#",
      data: tags,
      limit: 200,
      callbacks: {
		beforeInsert : function(value, $li){
			var returnvar = value;
			for (var key in tags_obj ) {
				
				if( value.trim().slice(1) == tags_obj[key] ) {
					returnvar = '#'+key;
				}
			}
			return returnvar;
		},
        afterMatchFailed: function(at, el) {
          // 32 is spacebar
          if (at == '#') {
            tags.push(el.text().trim().slice(1));
            this.model.save(tags);
            this.insert(el.text().trim());
            return false;
          }
        }
      }
    });
}); 
	</script>
	<?php
}
add_action('bp_before_activity_post_form','hb_create_hashtag');


// Saving data with the link.
function hb_update_activity_hash_tag( $content ) {
	global $bp;
	$pattern = '/[#]([\p{L}_0-9a-zA-Z-]+)/iu';

	preg_match_all( $pattern, $content, $hashtags );
	if ( $hashtags ) {
		if ( !$hashtags =  $hashtags[1] ) {
			return $content;
		}
		$slug = $bp->groups->current_group->slug;
		if( $slug == '' ){
			$slug = 'smartsocialwall';
		}
		$taxonamies1 = get_tag_taxonamies();
		$taxonamies = array_keys($taxonamies1);
		foreach( (array)$hashtags as $hashtag ) {
			if( $taxonamies ) {
				if( in_array( $hashtag, $taxonamies ) ) {
					
					$pattern = "/(^|\s|\b)#" . $hashtag ."($|\b)/";
					$activity_url = trailingslashit( get_bloginfo('url') ).'groups/'.$slug.'/?tag=' . $hashtag;
					$content = preg_replace( $pattern, ' <a href="' . $activity_url . '" target="_self" class="hashtag ' . htmlspecialchars( $hashtag ) . '">#'. htmlspecialchars( $taxonamies1[$hashtag] ) .'</a>', $content,1 );
					//$content = preg_replace( $pattern, ' <a href="' . $activity_url . '" target="_self" class="hashtag ' . htmlspecialchars( $hashtag ) . '">#'. htmlspecialchars( $hashtag ) .'</a>', $content,1 );
				}
			}
		}
	}
	return $content;
}


/* updating the tags into database*/
function hb_save_activity_has_tag ( $content, $user_id=0, $null=0, $activity_id ) {
	global $wpdb;
	$pattern = '/[#]([\p{L}_0-9a-zA-Z-]+)/iu';

	preg_match_all( $pattern, $content, $hashtags );
	if ( $hashtags ) {
		if ( !$hashtags = array_unique( $hashtags[1] ) ) {
			return $content;
		}
		
		// Delete tha tags added now
		$wpdb->query(  "DELETE FROM onevoice_activity_tags_details WHERE  activity_id = $activity_id  AND tag_id IN( SELECT tag_id FROM onevoice_activity_tags WHERE tag_type=0 )"  );
		$taxonamies1 = get_tag_taxonamies();
		$taxonamies = array_keys($taxonamies1);
		foreach( (array)$hashtags as $hashtag ) {
			
			
			if( $taxonamies ) {
				if( in_array( $hashtag, $taxonamies ) ) {
					$tag_id	=	$wpdb->get_var("SELECT tag_id FROM onevoice_activity_tags  WHERE tag_name ='$hashtag' AND tag_type=0");
			
					if ( $tag_id  >  0  ) {
						$wpdb->insert("onevoice_activity_tags_details", array( 
										 "activity_id" => "$activity_id",			                      
										 "tag_id"      => "$tag_id"
										  )); 
					} else {
						$wpdb->insert("onevoice_activity_tags", array( 
										 "tag_name" => "$hashtag",
										 "tag_type" => "0"
										  )); 
						$lastid = $wpdb->insert_id;	
						$wpdb->insert("onevoice_activity_tags_details", array( 
										 "activity_id" => "$activity_id",			                      
										 "tag_id"      => "$lastid"
										  )); 		   
					}
				}
			}
		}
	}
}
add_action( 'bp_groups_posted_update', 'hb_save_activity_has_tag', 10, 4 );
//added  in main class.php edit activity plugin  --> for updating the table on edit the content





function hb_auto_tagging(  $content, $user_id=0, $null=0, $activity_id ) {
	$taxonamies = get_tag_taxonamies();
	global $wpdb;
	if( ! $activity_id || ! $content ) {
		return false;
	}
	//$tag_lists = array();
	foreach( $taxonamies as $slug=>$taxonamy ) {
		//echo'<pre>'; var_dump($taxonamy);echo'</pre>'; 
		//echo'<pre>';  var_dump($content);echo'</pre>'; 
		//if( strpos($content, $taxonamy ) ) {
		$content 	=  strtolower( str_replace('/','',$content) );
		$taxonamy 	=  strtolower( str_replace('/','',$taxonamy) );
		if( preg_match('/\b'.$taxonamy.'\b/',$content) ) {
			//array_push($tag_lists,$taxonamy );
			$tag_id	=	$wpdb->get_var("SELECT tag_id FROM onevoice_activity_tags  WHERE tag_name ='$slug' AND tag_type=1");
			
			if ( $tag_id  >  0  ) {
				$tag_details_id	=	$wpdb->get_var("SELECT tag_id FROM onevoice_activity_tags_details  WHERE tag_id ='$tag_id' AND activity_id=$activity_id");
				if( $tag_details_id	> 0 ) {
					return;
				}
				$wpdb->insert("onevoice_activity_tags_details", array( 
									"activity_id" => "$activity_id",			                      
									"tag_id"      => "$tag_id"
									)); 
			} else {
				$wpdb->insert("onevoice_activity_tags", array( 
									"tag_name" => "$slug",
									"tag_type" => "1"
									)); 
				$lastid = $wpdb->insert_id;	
				$wpdb->insert("onevoice_activity_tags_details", array( 
									"activity_id" => "$activity_id",			                      
									"tag_id"      => "$lastid"
									)); 		   
			}

		}
	}
	//var_dump($tag_lists);
}
add_action( 'bp_groups_posted_update', 'hb_auto_tagging', 11, 4 );
 /*added  in main class.php edit activity plugin */
