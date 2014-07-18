<?php

class SearchPostContent extends Search
{
	function find ($pattern, $limit, $offset, $orderby)
	{
		global $wpdb;

		$results = array ();

		// @todo Make some of these conditions specifiable in the interface
		// I've abstracted the query a bit to make this easier to accomplish

		// Specify which post types to search
		$postTypeArgs = array( 'public' => true );
		$postTypes = get_post_types( $postTypeArgs, 'names' );

		// Query conditions
		$query_conditions = array();
		$query_conditions['select'] = "ID, post_content, post_title";
		$query_conditions['from'] = $wpdb->posts;
		$query_conditions['where'] = array();
		$query_conditions['where']['post_status'] = "!= 'inherit'";
		$query_conditions['where']['post_type'] = empty($postTypes) ? null : "IN ('".join("','", $postTypes)."')";
		$query_conditions['order by'] = "ID $orderby";

		// Write the query
		$sql_query = array();

		foreach ($query_conditions as $key => $value) {
			if (empty($value)) continue;
			if (is_array($value)) {
				$subconditions = array();
				foreach ($value as $k => $v) {
					if (!empty($v)) $subconditions[] = "$k $v";
				}
				$sql_query[] = "$key ". join(' AND ', $subconditions );
			} else {
				$sql_query[] = "$key $value";
			}
		}

		$sql_query = join(" ", $sql_query);

		$posts = $wpdb->get_results ( $sql_query );

		if ( $limit > 0 )
			$sql .= $wpdb->prepare( " LIMIT %d,%d", $offset, $limit );

		if (count ($posts) > 0)
		{
			foreach ($posts AS $post)
			{
				if (($matches = $this->matches ($pattern, $post->post_content, $post->ID)))
				{
					foreach ($matches AS $match)
						$match->title = $post->post_title;

					$results = array_merge ($results, $matches);
				}
			}
		}

		return $results;
	}

	function get_options ($result)
	{
		$options[] = '<a href="'.get_permalink ($result->id).'">'.__ ('view', 'search-regex').'</a>';
		if ($result->replace)
			$options[] = '<a href="#" onclick="regex_replace (\'SearchPostContent\','.$result->id.','.$result->offset.','.$result->length.',\''.str_replace ("'", "\'", $result->replace_string).'\'); return false">replace</a>';

		if (current_user_can ('edit_post', $result->id))
			$options[] = '<a href="'.get_bloginfo ('wpurl').'/wp-admin/post.php?action=edit&amp;post='.$result->id.'">'.__ ('edit','search-regex').'</a>';
		return $options;
	}

	function show ($result)
	{
		printf (__ ('Post #%d: %s', 'search-regex'), $result->id, $result->title);
	}

	function name () { return __ ('Post content', 'search-regex'); }

	function get_content ($id)
	{
		global $wpdb;

		$post = $wpdb->get_row ( $wpdb->prepare( "SELECT post_content FROM {$wpdb->prefix}posts WHERE id=%d", $id ) );
		return $post->post_content;
	}

	function replace_content ($id, $content)
	{
		global $wpdb;
		$wpdb->query ($wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content=%s WHERE ID=%d", $content, $id ) );
	}
}
