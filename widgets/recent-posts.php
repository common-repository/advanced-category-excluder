<?php
/*
Plugin Name: Advanced Category Excluder Widgets
Version: 1.5
Plugin URI: http://ace.dev.rain.hu
Description: This plugin adds some basic widgetsm that support category exclusion
Author: DjZoNe
Author URI: http://djz.hu/
*/

/** 
  * The display code is from includes/widgets.php from version 2.6
  */


/**
 * Recent_Posts widget class
 *
 * @since 2.8.0
 */
class WP_Widget_Recent_Posts extends WP_Widget {

	function WP_Widget_Recent_Posts() {
		$widget_ops = array('classname' => 'widget_recent_entries', 'description' => __( "The most recent posts on your blog") );
		$this->WP_Widget('recent-posts', __('Recent Posts'), $widget_ops);
		$this->alt_option_name = 'widget_recent_entries';

		add_action( 'save_post', array(&$this, 'flush_widget_cache') );
		add_action( 'deleted_post', array(&$this, 'flush_widget_cache') );
		add_action( 'switch_theme', array(&$this, 'flush_widget_cache') );
	}

	function widget($args, $instance) {
		$cache = wp_cache_get('widget_recent_posts', 'widget');

		if ( !is_array($cache) )
			$cache = array();

		if ( isset($cache[$args['widget_id']]) ) {
			echo $cache[$args['widget_id']];
			return;
		}

		ob_start();
		extract($args);

		$title = apply_filters('widget_title', empty($instance['title']) ? __('Recent Posts') : $instance['title']);
		if ( !$number = (int) $instance['number'] )
			$number = 10;
		else if ( $number < 1 )
			$number = 1;
		else if ( $number > 15 )
			$number = 15;

		$r = new WP_Query(array('showposts' => $number, 'nopaging' => 0, 'post_status' => 'publish', 'caller_get_posts' => 1));
		if ($r->have_posts()) :
?>
		<?php echo $before_widget; ?>
		<?php if ( $title ) echo $before_title . $title . $after_title; ?>
		<ul>
		<?php  while ($r->have_posts()) : $r->the_post(); ?>
		<li><a href="<?php the_permalink() ?>" title="<?php echo esc_attr(get_the_title() ? get_the_title() : get_the_ID()); ?>"><?php if ( get_the_title() ) the_title(); else the_ID(); ?> </a></li>
		<?php endwhile; ?>
		</ul>
		<?php echo $after_widget; ?>
<?php
			wp_reset_query();  // Restore global post data stomped by the_post().
		endif;

		$cache[$args['widget_id']] = ob_get_flush();
		wp_cache_add('widget_recent_posts', $cache, 'widget');
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$this->flush_widget_cache();

		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset($alloptions['widget_recent_entries']) )
			delete_option('widget_recent_entries');

		return $instance;
	}

	function flush_widget_cache() {
		wp_cache_delete('widget_recent_posts', 'widget');
	}

	function form( $instance ) {
		$title = isset($instance['title']) ? esc_attr($instance['title']) : '';
		if ( !isset($instance['number']) || !$number = (int) $instance['number'] )
			$number = 5;
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of posts to show:'); ?></label>
		<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /><br />
		<small><?php _e('(at most 15)'); ?></small></p>
<?php
	}
} 

class AceRecentPostsWidget 
{
  /**
   * Default values
   */
  
  var $title = '';
  var $count = '0';
  var $hierarchical = '0';
  var $dropdown = '0';  

  // static init callback
  function init() 
  {
    // Check for the required plugin functions. This will prevent fatal
    // errors occurring when you deactivate the dynamic-sidebar plugin.
    if ( !function_exists('register_sidebar_widget') )
      return;

    $widget = new AceRecentPostsWidget();

    // This registers our widget so it appears with the other available
    // widgets and can be dragged and dropped into any active sidebars.
    register_sidebar_widget('ACE Recent Posts', array($widget,'display'));

    // This registers our optional widget control form.
    register_widget_control('ACE Recent Posts', array($widget,'control'), 280, 300);
  }

  function control() {
    // Get our options and see if we're handling a form submission.
    $options = get_option('ace_widget_recent_posts');
      
    if ( !is_array($options) )
      $options = array('title'=>'',
		       'count' => $this->count,
		       'hierarchical' => $this->hierarchical,
		       'dropdown' => $this->dropdown );
    
    
    if ( !empty($_POST['ace-recent-posts-submit']) ) 
    {
    
			$options['title'] = trim(strip_tags(stripslashes($_POST['ace-recent-posts-title'])));
			$options['number'] = (int) $_POST['ace-recent-posts-number'];
			
			if ($options['number'] > 15) $options['number'] = 15;//The limit

		  update_option('ace_widget_recent_posts', $options);
     }    

		$title = attribute_escape( $options['title'] );
		$number = (int) $options['number'];


?>
			<p>
				<label for="recent-posts-title">
					<?php _e( 'Title:' ); ?>
					<input class="widefat" id="recent-posts-title" name="ace-recent-posts-title" type="text" value="<?php echo $title; ?>" />
				</label>
			</p>

			<p>
				<label for="recent-posts-number"><?php _e('Number of posts to show:'); ?> <input style="width: 25px; text-align: center;" id="recent-posts-number" name="ace-recent-posts-number" type="text" value="<?php echo $number; ?>" />
				</label>
				<br />
				<small><?php _e('(at most 15)'); ?></small>
			</p>

			<input type="hidden" name="ace-recent-posts-submit" value="1" />
<?php
  }

  function display($args) 
  {
    global $wpdb, $wp_query, $ace_targets;
    // $args is an array of strings that help widgets to conform to
    // the active theme: before_widget, before_title, after_widget,
    // and after_title are the array keys. Default tags: li and h2.
    extract($args);

    $options = get_option('ace_widget_recent_posts');
      
    if ( !is_array($options) )
      $options = array('title'=>'',
		       'number' => $this->number );

  $cats_to_exclude = '';


	$title = empty($options['title']) ? __('Recent Posts') : apply_filters('widget_title', $options['title']);
	$number = $options['number'];

/* Here comes ACE patch ;) */


  
  /**
   * If we got categories to exclude, we want negative values of them
   * because WP_Query requires negative values, in a comma separeted list
   * to the 'cat' value.      
   */     
  if (!empty($cats_to_exclude))
  {
    $cats = array();
    foreach(explode(',',$cats_to_exclude) as $category )
    {
      $cats[]=0-$category;
    }
    
    /**
     * Yes, we overwrite here.
     */         
    $cats_to_exclude = implode(",",$cats);
  }

    /**
     * Suppress filters is IMPORTANT
     * 
     * This widget now is better than the original. 
     * Not joking ;)          
     */         
    $r = new WP_Query(array('showposts' => $number, 'what_to_show' => 'posts', 'nopaging' => 0, 'post_status' => 'publish','cat'=>$cats_to_exclude,'suppress_filters'=>1));
  	
  	if ($r->have_posts()) :
  ?>
  		<?php echo $before_widget; ?>
  			<?php echo $before_title . $title . $after_title; ?>
  			<ul>
  			<?php  while ($r->have_posts()) : $r->the_post(); ?>
  			<li><a href="<?php the_permalink() ?>"><?php if ( get_the_title() ) the_title(); else the_ID(); ?> </a></li>
  			<?php endwhile; ?>
  			</ul>
  		<?php echo $after_widget; ?>
  <?php
  		wp_reset_query();  // Restore global post data stomped by the_post().
  	endif;
    }
}

add_action('widgets_init', array('AceRecentPostsWidget','init'));

?>