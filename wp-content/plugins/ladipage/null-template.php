<?php
/*
Template Name: Null Template - LadiPage
Template Post Type: post, page, product, property
*/
spl_autoload_unregister('autoptimize_autoload');
/*
while ( have_posts() ) : the_post();	
	the_content();
endwhile;
*/
$sql = sprintf("SELECT post_title, post_name, post_content FROM %s WHERE ID = %s", $wpdb->posts, get_the_ID());
$post = $wpdb->get_row($sql);
$content = $post->post_content;
$content = str_replace('< !DOCTYPE html>', '<!DOCTYPE html>', $content);
echo $content;
?>