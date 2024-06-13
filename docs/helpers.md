# Helpers

There are many helper functions used throughout the plugin which may come in handy either while working on the plugin or outside of it (i.e. theme development).

Firstly the class must be instantiated:

```php
$helpers = new \nine3migrate\Nine3_Helpers();
```

Then you can call any of the methods: `$helpers->method();`

## Return data array from JSON or CSV file

Accepts the file path of a JSON/CSV file and returns a decoded data array.

### Usage

```php
$data_array = return_file_data( $file );
```

### Params

| Variable | Type | Description |
|---|---|---|
| $file | string | The full path of the JSON/CSV file |
| $first | boolean | _Default: false._ If true, returns only the first element of array |

## Remove Divi from string

Accepts a string of HTML content and returns it with all Divi shortcodes removed.

### Usage

```php
$content = remove_divi_shortcodes( $content );
```

### Params

| Variable | Type | Description |
|---|---|---|
| $content | string | HTML content |

## Remove inline styles from string

Accepts a string of HTML content and returns it with all inline styles removed.

### Usage

```php
$content = remove_inline_styles( $content );
```

### Params

| Variable | Type | Description |
|---|---|---|
| $content | string | HTML content |

## Remove classes from string

Accepts a string of HTML content and returns it with all classes removed.

### Usage

```php
$content = remove_inline_classes( $content );
```

### Params

| Variable | Type | Description |
|---|---|---|
| $content | string | HTML content |

## Upload and replace images and files

Scans the HTML content string for `<img>` tags, strips the `src` attribute, then uploads the image and replaces the `src` with the new value.

Also does this for `<a>` tags in which the `href` attribute contains document extensions (i.e. `.pdf`, `.docx`, `.xlsx`, etc.).

### Usage

```php
$content = upload_and_replace_images( $content );
```

### Params

| Variable | Type | Description |
|---|---|---|
| $content | string | HTML content |

## Upload file

Accepts an image (or file) URL, uploads it to the current media library and returns either an attachment ID or new file URL. Can also set as featured image.

### Usage

```php
// Set new featured image.
upload_image( $image, $post_id, true );

// Upload and return new URL.
$image_url = upload_image( $image, 0, false, true );
```

### Params

| Variable | Type | Description |
|---|---|---|
| $image | string | Full URL of file |
| $parent_id | integer | _Default: 0_. Post ID to attach the image to  |
| $is_featured | boolean | _Default: false_. Should the image be set as the featured image of this post |
| $return_url | boolean | _Default: false_. Should the function return the URL or the attachment ID |

## Debugger

Logs or dumps data.

### Usage

```php
debug( $data, 'log', true );
```

### Params

| Variable | Type | Description |
|---|---|---|
| $data | mixed | Any data we want to log/display |
| $type | string | _Default: display_. `display` = `var_dump()`, `log` = `error_log()` |
| $exit | boolean | _Default: false_. Should we call `exit()` after logging |