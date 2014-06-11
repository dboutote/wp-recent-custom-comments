<?php
/**
 * Plugin Name: Widget: Recent Custom Comments
 * Plugin URI:
 * Description: A sidebar Widget for displaying the latest comments.  Allows you to select content type to display.
 * Version: 1.0
 * Author: Darrin Boutote
 * Author URI: http://darrinb.com
 * Tags: custom post types, post types, latest posts, sidebar widget, plugin
 * License: GPL
 */


/**
 * Activate:
 */
add_action( 'plugins_loaded', array( 'Widget_Recent_Custom_Comments', 'enable_multi_posttypes' ) );

/**
 * Add function to widgets_init that will load our widget
 */
add_action( 'widgets_init', 'widget_recent_custom_contents_load_widget' );

/**
 * Register our widget
 */
function widget_recent_custom_contents_load_widget() {
    register_widget( 'Widget_Recent_Custom_Comments' );
    unregister_widget( 'WP_Widget_Recent_Comments' ); // unregister the default Recent Comments widget
}

/**
 * Recent_Custom_Comments widget class
 *
 * Filters out comments with data stored in the wp_commentmeta table
 */

if ( !class_exists('Widget_Recent_Custom_Comments') ) {

    class Widget_Recent_Custom_Comments extends WP_Widget {

		public function __construct(){
            // Widget settings
            $widget_ops = array(
                'classname' => 'widget-recent-custom-comments',                     // classname: added to containing html markup for the widget
                'description' => __('The most recent comments by content type.')    // description: shown on the configuration page
            );

            // Create the widget
            $this->WP_Widget(
                'recent-custom-comments',   // id base
                __('Recent Comments'),      // name
                $widget_ops
            );

            $this->alt_option_name = 'widget_recent_custom_comments';

			add_action( 'comment_post', array($this, 'flush_widget_cache') );
			add_action( 'edit_comment', array($this, 'flush_widget_cache') );
			add_action( 'transition_comment_status', array($this, 'flush_widget_cache') );
			add_filter( 'comments_clauses', array( $this, 'enable_multi_posttypes' ), 99, 2 );
        }


		public function enable_multi_posttypes( $clauses, $wpqc='' ){
			global $wpdb;

			if( isset( $wpqc->query_vars['post_type'][0] ) ) {
				$join = join( "', '", array_map( 'esc_sql', $wpqc->query_vars['post_type'] ) );

				$replace = sprintf( "$wpdb->posts.post_type = '%s'", $wpqc->query_vars['post_type'][0] );
				$replaceWidth   = sprintf( "$wpdb->posts.post_type IN ( '%s' ) ", $join );

				$clauses['where'] = str_replace( $replace, $replaceWidth, $clauses['where'] );
			}

			return $clauses;
		}



		// flush cache
		function flush_widget_cache() {
			wp_cache_delete('widget_recent_custom_comments', 'widget');
		}


        // display the widget on the front-end
        public function widget( $args, $instance ) {
            global $comments, $comment;

			$cache = wp_cache_get('widget_recent_custom_comments', 'widget');

			if ( ! is_array( $cache ) ) {
				$cache = array();
			}

			if ( ! isset( $args['widget_id'] ) ) {
				$args['widget_id'] = $this->id;
			}

			if ( isset( $cache[ $args['widget_id'] ] ) ) {
				echo $cache[ $args['widget_id'] ];
				return;
			}

            extract($args, EXTR_SKIP);

			$output = '';

			$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : __( 'Recent Comments' );
			$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

			if ( !$number = absint( $instance['number'] ) ) {
                $number = 5;
			} else if ( $number < 1 ) {
                $number = 1;
			} else if ( $number > 10 ) {
                $number = 10;
			}

			$post_types = ( !is_array($instance['posttype']) ) ? (array)$instance['posttype']: $instance['posttype'] ;

			$comments = get_comments(
				apply_filters(
					'widget_comments_args',
					array(
						'post_type' => $post_types,
						'number' => $number,
						'status' => 'approve',
						'post_status' => 'publish'
					)
				)
			);

            if ( $comments ) {

                $output .= $before_widget;

                if ( $title ) {
                    $output .= $before_title . $title . $after_title;
				}

                $output .= '<ul id="recentcomments">';

				// Prime cache for associated posts. (Prime post term cache if we need it for permalinks.)
				$post_ids = array_unique( wp_list_pluck( $comments, 'comment_post_ID' ) );
				_prime_post_caches( $post_ids, strpos( get_option( 'permalink_structure' ), '%category%' ), false );

				foreach ( (array) $comments as $comment) {

					ob_start(); 
					?>
						<li <?php comment_class(); ?> id="li-widget-comment-<?php comment_ID(); ?>">
							<article id="widget-comment-<?php echo $comment->comment_ID; ?>" class="comment">
								<header class="comment-meta comment-author vcard">
									<?php
										if ( $instance['show_thumbs']  ) {
											echo get_avatar( $comment, 40 );
										}
										printf( '<cite><b class="fn">%1$s</b></cite>', get_comment_author_link($comment->comment_ID) );
										printf( '<a href="%1$s"><time datetime="%2$s">%3$s</time></a>',
											esc_url( get_comment_link($comment->comment_ID) ),
											get_comment_time( 'c' ),
											/* translators: 1: date, 2: time */
											sprintf( __( '%1$s at %2$s', 'twentytwelve' ), get_comment_date(), get_comment_time() )
										);
									?>
								</header><!-- .comment-meta -->

								<section class="comment-content comment">
									<?php echo get_comment_excerpt($comment->comment_ID); ?>
								</section><!-- .comment-content -->

							</article><!-- #comment-## -->
						</li>
					<?php
					$output .= ob_get_contents();
					ob_end_clean();
				}

                $output .= '</ul>';
                $output .= $after_widget;
            }

			echo $output;
			$cache[$args['widget_id']] = $output;
			wp_cache_set('widget_recent_custom_comments', $cache, 'widget');

        }

        // updates widget settings
        function update( $new_instance, $old_instance ) {
            $instance                = $old_instance;
            $instance['title']       = strip_tags($new_instance['title']);
            $instance['number']      = (int) $new_instance['number'];
            $instance['posttype']    = $new_instance['posttype'];
            $instance['show_thumbs'] = $new_instance['show_thumbs']    ? 1 : 0;
            return $instance;
        }

        // display widget form
        function form( $instance ) {

            $instance = wp_parse_args( (array) $instance,
                array(
                      'posttype'    => array('post'),
                      'title'       => 'Recently Discussed',
                      'number'      => 5,
                      'show_thumbs' => 1
                )
            );

            $title = isset($instance['title']) ? esc_attr($instance['title']) : '';
            $number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
            ?>
			
            <p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
			</p>

            <p>
				Select Content Type:<br />
                <?php
				// get all public post types
				$post_types = get_post_types( array( 'public' => true ), 'objects'  );

				foreach ( $post_types as $post_type ) {
					$post_type_array[$post_type->labels->singular_name] = ( ''!= $post_type->query_var ) ? $post_type->query_var : $post_type->name ;
				}

				ksort($post_type_array);

				foreach ( $post_type_array as $postname => $query_var ) { ?>
					<input class="checkbox" type="checkbox" id="<?php echo $this->get_field_id('posttype-'. $query_var); ?>" name="<?php echo $this->get_field_name('posttype').'[]'; ?>" value="<?php echo $query_var; ?>" <?php echo ( is_array($instance['posttype']) && in_array($query_var, $instance['posttype']) ) ? 'checked="checked" ' :''; ?>/>
					<label for="<?php echo $this->get_field_id('posttype-'. $query_var); ?>"><?php _e( $postname ); ?></label><br />
				<?php }; ?>
            </p>

            <p>
				<label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of comments to show:'); ?></label>
				<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" />
			</p>

            <p>
				<input class="checkbox" type="checkbox" id="<?php echo $this->get_field_id('show_thumbs'); ?>" name="<?php echo $this->get_field_name('show_thumbs'); ?>" <?php checked( $instance['show_thumbs'], 1 ); ?>/> 
				<label for="<?php echo $this->get_field_id('show_thumbs'); ?>"><?php _e('Show Avatars'); ?></label>
				</p>

        <?php }

    }

};