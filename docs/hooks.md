# Hooks

This section details all the hooks available in the plugin which allows a third party to modify the import process. Currently there are two filter hooks in the plugin:

## Post Content Filter

This filter allows a third party to inject custom HTML into post content. This is useful when multiple data keys are inserted into post content or if we just want to append/prepend some extra HTML. We can use this filter to pick out these data keys and modify the values inserted.

### Usage

```php
add_filter( 'nine3_migrate_post_content', [ $this, 'migrate_inject_html' ], 10, 4 );
```

```php
/**
 * 'nine3_migrate_post_content' filter hook callback
 * Allows us to modify post content html for each key inserted into post content
 *
 * @param string $content the post content string.
 * @param string $key which key is currently being added.
 * @param string $post_type CPT of the current import.
 * @param array  $post_data array of post data from import file.
 */
public function migrate_inject_html( $content, $key, $post_type, $post_data ) {
	// Only fire on post cpt.
	if ( $post_type === 'post' ) {
		// If this key was added in post content, wrap its content in a custom Gutenberg block.
		if ( $key === 'post_title' ) {
			$content = '<!-- wp:luna/m01-post-title-block /-->' . $content . '<!-- /wp:luna/m01-post-title-block /-->';
		}

		// Append social sharing block to post content.
		if ( $key === 'post_content' ) {
			$content .= '<!-- wp:luna/m20-share-links /-->';
		}
	}

	return $content;
}
```

## Before Insert Data

This filter fires just before the `wp_insert_post()` function is run in the import and allows us to see and modify the entire data array being inserted. This is very useful for gathering and parsing meta data and injecting it into blocks inside `$data['post_content']`.

If an import ID settings is set this filter can be used to target individual imports, e.g. if the ID is set to `resources-posts` you can use the filter like so: `add_filter( 'nine3_migrate_before_insert_data__resources-posts', [ $this, 'migrate_resources' ], 10, 1 );`

### Usage

```php
add_filter( 'nine3_migrate_before_insert_data', [ $this, 'migrate_modify_post_data' ], 10, 1 );
add_filter( 'nine3_migrate_before_insert_data__[ID]', [ $this, 'migrate_modify_post_data' ], 10, 1 );
```

```php
/**
 * 'nine3_migrate_before_insert_data' filter hook callback
 * Allows us to modify post data array before inserting post
 *
 * @param array $post_data array post data about to be inserted.
 */
public function migrate_modify_post_data( $post_data ) {
	// Change post_status to draft.
	$post_data['post_status'] = 'draft';

	return $post_data;
}
```