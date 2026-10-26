# Agent Guidelines & WordPress Best Practices for Gear Engine

This document outlines the coding standards, security requirements, and best practices that all AI agents and contributors must follow when developing and maintaining the **Gear Engine** codebase.

---

## 1. Direct File Access Prevention (ABSPATH)

Every PHP file in the project **must** begin with a check to prevent direct execution outside of WordPress. Place this check immediately after the opening `<?php` tag (or after file-level DocBlock):

```php
<?php
/**
 * File description and package information.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
```

*Alternative allowed syntax:*
```php
defined( 'ABSPATH' ) || exit;
```

---

## 2. Mandatory Prefixing: `gear_engine_`

To prevent naming collisions with WordPress core, themes, and third-party plugins, **all global identifiers must use the unique prefix `gear_engine_`**:

- **Functions:** Must start with `gear_engine_` (e.g., `gear_engine_setup()`, `gear_engine_register_post_types()`, `gear_engine_get_option()`).
- **Classes, Interfaces, and Traits:** Must be prefixed with `gear_engine_` or `Gear_Engine_` (or organized under a namespace like `Gear_Engine\` or `GearEngine\`):
  ```php
  class Gear_Engine_Admin {
      // ...
  }

  // Or namespaced:
  namespace Gear_Engine\Admin;
  
  class Settings_Page {
      // ...
  }
  ```
- **Constants:** Must start with `GEAR_ENGINE_` in all caps:
  ```php
  define( 'GEAR_ENGINE_VERSION', '1.0.0' );
  define( 'GEAR_ENGINE_PATH', plugin_dir_path( __FILE__ ) );
  define( 'GEAR_ENGINE_URL', plugin_dir_url( __FILE__ ) );
  ```
- **Action & Filter Hooks:** Custom hooks must be prefixed with `gear_engine_`:
  ```php
  do_action( 'gear_engine_before_render' );
  $value = apply_filters( 'gear_engine_filter_settings', $value );
  ```
- **Database Tables & Options:**
  - Options: `gear_engine_*` (e.g., `get_option( 'gear_engine_settings' )`).
  - Transients: `gear_engine_*` (e.g., `get_transient( 'gear_engine_cached_feed' )`).
  - Custom tables: `$wpdb->prefix . 'gear_engine_*'`.
- **Enqueued Assets:**
  - Script & style handles: `gear-engine-*` or `gear_engine_*` (e.g., `wp_enqueue_style( 'gear-engine-admin', ... )`).

---

## 3. Input Sanitization (Sanitize Early)

**Never trust input data.** All data received from superglobals (`$_GET`, `$_POST`, `$_REQUEST`, `$_SERVER`), REST requests, or external APIs must be validated and sanitized immediately before usage or storage.

### Rules for Input Handling:
1. Always unslash data before sanitizing when accessing WordPress superglobals (`wp_unslash()`).
2. Choose the most specific sanitization function for the data type.

### Common Sanitization Functions:
| Data Type | Function / Technique | Example |
|---|---|---|
| Single-line Text | `sanitize_text_field()` | `sanitize_text_field( wp_unslash( $_POST['title'] ) )` |
| Multi-line Text | `sanitize_textarea_field()` | `sanitize_textarea_field( wp_unslash( $_POST['bio'] ) )` |
| Email Address | `sanitize_email()` | `sanitize_email( wp_unslash( $_POST['email'] ) )` |
| Key / Slug | `sanitize_key()` | `sanitize_key( wp_unslash( $_POST['item_key'] ) )` |
| Post Slug / Title | `sanitize_title()` | `sanitize_title( wp_unslash( $_POST['slug'] ) )` |
| File Name | `sanitize_file_name()` | `sanitize_file_name( wp_unslash( $_FILES['upload']['name'] ) )` |
| URL (for DB/API) | `esc_url_raw()` | `esc_url_raw( wp_unslash( $_POST['website'] ) )` |
| Hex Color | `sanitize_hex_color()` | `sanitize_hex_color( wp_unslash( $_POST['accent_color'] ) )` |
| Integer / ID | `absint()` or `intval()` | `absint( $_POST['post_id'] )` |
| Float / Decimal | `floatval()` | `floatval( $_POST['price'] )` |
| HTML / Rich Text | `wp_kses_post()` or `wp_kses()` | `wp_kses_post( wp_unslash( $_POST['content'] ) )` |
| Array of Items | `array_map()` with callback | `array_map( 'sanitize_text_field', wp_unslash( $_POST['items'] ) )` |

### Input Sanitization Example:
```php
function gear_engine_save_profile_settings() {
    // 1. Verify nonce and user capability first
    if ( ! isset( $_POST['gear_engine_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['gear_engine_nonce'] ), 'gear_engine_save_profile' ) ) {
        wp_die( esc_html__( 'Security check failed.', 'gear-engine' ), '', array( 'response' => 403 ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Unauthorized user.', 'gear-engine' ), '', array( 'response' => 403 ) );
    }

    // 2. Unslash and sanitize inputs
    $user_id   = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    $full_name = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
    $bio       = isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : '';
    $website   = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';

    // 3. Process or save sanitized data
    update_user_meta( $user_id, 'gear_engine_full_name', $full_name );
    update_user_meta( $user_id, 'gear_engine_bio', $bio );
    update_user_meta( $user_id, 'gear_engine_website', $website );
}
```

---

## 4. Output Escaping (Escape Late)

**Escape all dynamic data at the point of rendering (late escaping).** Do not escape data upon storing in variables or passing to functions; escape as late as possible right before it is echoed into HTML, HTML attributes, JavaScript, or URLs.

### Escaping Rules & Contexts:
| Context | Function | Example |
|---|---|---|
| Plain HTML text | `esc_html()` | `<p><?php echo esc_html( $gear_engine_text ); ?></p>` |
| HTML tag attributes | `esc_attr()` | `<input type="text" value="<?php echo esc_attr( $gear_engine_val ); ?>">` |
| URLs (`href`, `src`) | `esc_url()` | `<a href="<?php echo esc_url( $gear_engine_link ); ?>">` |
| Textarea content | `esc_textarea()` | `<textarea><?php echo esc_textarea( $gear_engine_body ); ?></textarea>` |
| JavaScript strings | `esc_js()` | `<script>let label = "<?php echo esc_js( $gear_engine_str ); ?>";</script>` |
| JSON output | `wp_json_encode()` | `wp_send_json_success( $gear_engine_data );` |
| Allowed HTML / Posts | `wp_kses_post()` | `<div><?php echo wp_kses_post( $gear_engine_html ); ?></div>` |
| Custom HTML elements | `wp_kses()` | `wp_kses( $gear_engine_content, array( 'strong' => array(), 'em' => array() ) )` |

### Escaping with Localization:
When translating strings that contain dynamic values or attributes:
- `esc_html__( 'Text', 'gear-engine' )` - returns escaped translated string.
- `esc_html_e( 'Text', 'gear-engine' )` - echoes escaped translated string.
- `esc_attr__( 'Text', 'gear-engine' )` - returns escaped attribute translated string.
- `esc_attr_e( 'Text', 'gear-engine' )` - echoes escaped attribute translated string.
- Use `sprintf()` when combining translated strings with variables:
  ```php
  printf(
      /* translators: %s: Author name */
      esc_html__( 'Created by %s', 'gear-engine' ),
      esc_html( $gear_engine_author_name )
  );
  ```

---

## 5. Security & Authorization

### Nonces (CSRF Protection)
- Every form submission, AJAX request, and administrative action must be protected with a cryptographic nonce.
- Create nonces with `wp_nonce_field()` or `wp_create_nonce()`.
- Verify nonces with:
  - Form actions: `check_admin_referer( 'gear_engine_action', 'gear_engine_nonce_field' )`
  - AJAX requests: `check_ajax_referer( 'gear_engine_action', 'nonce' )`
  - Custom / REST: `wp_verify_nonce( $nonce, 'gear_engine_action' )`

### Capability Checks (Access Control)
- Nonces prevent CSRF, but **do not** authenticate or authorize users.
- Always check capabilities explicitly before taking action:
  ```php
  if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'You do not have permission to access this page.', 'gear-engine' ), '', array( 'response' => 403 ) );
  }
  ```

---

## 6. Database Queries & `$wpdb` Preparation

Never concatenate or interpolate dynamic variables into SQL queries. Always use `$wpdb->prepare()`:

```php
global $wpdb;

$table_name = $wpdb->prefix . 'gear_engine_items';

// Safe query preparation with placeholders (%s for string, %d for integer, %f for float):
$safe_query = $wpdb->prepare(
    "SELECT id, name, status FROM {$table_name} WHERE status = %s AND user_id = %d LIMIT %d",
    $status,
    $user_id,
    $limit
);

$results = $wpdb->get_results( $safe_query );
```

For standard CRUD operations, use `$wpdb` helper functions:
```php
$wpdb->insert(
    $table_name,
    array(
        'name'       => $name,
        'user_id'    => $user_id,
        'created_at' => current_time( 'mysql' ),
    ),
    array( '%s', '%d', '%s' )
);
```

---

## 7. WordPress Architecture & Code Organization

1. **Hook-Driven Architecture:**
   - Avoid executing code directly on file include. Attach logic to WordPress hooks (`plugins_loaded`, `init`, `admin_init`, `wp_enqueue_scripts`, etc.).
   - Example:
     ```php
     add_action( 'init', 'gear_engine_init' );
     function gear_engine_init() {
         // Initialization logic here.
     }
     ```

2. **Asset Enqueuing:**
   - Register and enqueue scripts and styles using standard hooks:
     ```php
     add_action( 'wp_enqueue_scripts', 'gear_engine_enqueue_assets' );
     function gear_engine_enqueue_assets() {
         wp_enqueue_style(
             'gear-engine-styles',
             GEAR_ENGINE_URL . 'assets/css/gear-engine.css',
             array(),
             GEAR_ENGINE_VERSION
         );

         wp_enqueue_script(
             'gear-engine-scripts',
             GEAR_ENGINE_URL . 'assets/js/gear-engine.js',
             array( 'jquery' ),
             GEAR_ENGINE_VERSION,
             true // In footer
         );
     }
     ```

3. **Internationalization (i18n):**
   - Text domain: `'gear-engine'`
   - Load textdomain on `init` or `plugins_loaded`:
     ```php
     load_plugin_textdomain( 'gear-engine', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
     ```

4. **Error Handling:**
   - Use `WP_Error` objects for recoverable errors and check with `is_wp_error()`.
   - Never expose raw exceptions or database errors to the user.

---

## 8. Agent Checklist

Before completing any task or committing changes in this repository, verify:
- [ ] Direct file access check (`ABSPATH`) is present at the beginning of all PHP files.
- [ ] All functions, classes, traits, interfaces, constants, and hooks use the `gear_engine_` (or `GEAR_ENGINE_`) prefix.
- [ ] All inputs (`$_GET`, `$_POST`, `$_REQUEST`, etc.) are unslashed with `wp_unslash()` and sanitized with appropriate WordPress sanitization functions.
- [ ] All outputs rendered in HTML, attributes, JS, or URLs are escaped as late as possible (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`, etc.).
- [ ] Capability checks (`current_user_can()`) and nonce verifications (`check_admin_referer`, `check_ajax_referer`, `wp_verify_nonce`) protect sensitive actions.
- [ ] All custom database queries use `$wpdb->prepare()`.
- [ ] All translatable strings use the text domain `'gear-engine'`.
