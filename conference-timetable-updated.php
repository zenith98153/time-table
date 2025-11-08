<?php
/**
 * Plugin Name: Conference Timetable (6 Tracks)
 * Description: Time-scaled timetable with 6 parallel tracks per day. Click events to view details in a centered modal. Shortcode: [ctt_timetable date="YYYY-MM-DD" start="09:00" end="18:00" slot="30"].
 * Version: 2.0.1
 * Author: You
 * License: GPL2+
 */

if (!defined('ABSPATH')) exit;

class CTT_Plugin {
    const CPT      = 'ctt_event';
    const CPT_INFO = 'ctt_info_block';
    const TAX      = 'ctt_event_category';
    const NS       = 'ctt/v1';

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('add_meta_boxes', [$this, 'add_metaboxes']);
        add_action('save_post', [$this, 'save_meta']);
        add_shortcode('ctt_timetable', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_filter('manage_'.self::CPT.'_posts_columns', [$this, 'admin_columns']);
        add_action('manage_'.self::CPT.'_posts_custom_column', [$this, 'admin_column_content'], 10, 2);
        add_filter('manage_'.self::CPT_INFO.'_posts_columns', [$this, 'admin_columns_info']);
        add_action('manage_'.self::CPT_INFO.'_posts_custom_column', [$this, 'admin_column_content_info'], 10, 2);
        
        // Elementor dynamic tags support
        add_action('elementor/dynamic_tags/register', [$this, 'register_elementor_tags']);
        
        register_activation_hook(__FILE__, [$this, 'on_activate']);
    }

    /*** CPT & Taxonomy ***/
    public function register_cpt() {
        // Register taxonomy first
        register_taxonomy(self::TAX, [self::CPT], [
            'labels' => [
                'name'          => 'Event Categories',
                'singular_name' => 'Event Category',
                'search_items'  => 'Search Categories',
                'all_items'     => 'All Categories',
                'edit_item'     => 'Edit Category',
                'update_item'   => 'Update Category',
                'add_new_item'  => 'Add New Category',
                'new_item_name' => 'New Category Name',
                'menu_name'     => 'Categories',
            ],
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'event-category'],
            'show_in_rest'      => true,
        ]);

        // Events
        register_post_type(self::CPT, [
            'labels' => [
                'name'          => 'Timetable Events',
                'singular_name' => 'Timetable Event',
                'add_new_item'  => 'Add Timetable Event',
                'edit_item'     => 'Edit Timetable Event',
                'new_item'      => 'New Timetable Event',
                'view_item'     => 'View Timetable Event',
                'search_items'  => 'Search Timetable Events',
                'not_found'     => 'No events found',
                'menu_name'     => 'Timetable',
            ],
            'public'      => true,
            'show_ui'     => true,
            'menu_icon'   => 'dashicons-schedule',
            'supports'    => ['title','editor'],
            'taxonomies'  => [self::TAX],
            'show_in_rest' => true,
            'has_archive' => false,
        ]);

        // Info Blocks
        register_post_type(self::CPT_INFO, [
            'labels' => [
                'name'          => 'Info Blocks',
                'singular_name' => 'Info Block',
                'add_new_item'  => 'Add Info Block',
                'edit_item'     => 'Edit Info Block',
                'new_item'      => 'New Info Block',
                'view_item'     => 'View Info Block',
                'search_items'  => 'Search Info Blocks',
                'not_found'     => 'No info blocks found',
                'menu_name'     => 'Info Blocks',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'edit.php?post_type='.self::CPT,
            'menu_icon'    => 'dashicons-info',
            'supports'     => ['title'],
            'show_in_rest' => true,
        ]);
    }

    /*** Metaboxes ***/
    public function add_metaboxes() {
        add_meta_box('ctt_event_details', 'Event Details', [$this,'render_metabox'], self::CPT, 'normal', 'high');
        add_meta_box('ctt_info_details', 'Info Block Details', [$this,'render_info_metabox'], self::CPT_INFO, 'normal', 'high');
    }

    public function render_metabox($post) {
        wp_nonce_field('ctt_save_meta','ctt_nonce');
        $date       = get_post_meta($post->ID, '_ctt_date', true);
        $start      = get_post_meta($post->ID, '_ctt_start', true);
        $end        = get_post_meta($post->ID, '_ctt_end', true);
        $track      = get_post_meta($post->ID, '_ctt_track', true);
        $track_span = (int) (get_post_meta($post->ID, '_ctt_track_span', true) ?: 1);
        $speaker    = get_post_meta($post->ID, '_ctt_speaker', true);
        $room       = get_post_meta($post->ID, '_ctt_room', true);
        $org_img_id = (int) get_post_meta($post->ID, '_ctt_organizer_img', true);
        $org_img_url= $org_img_id ? wp_get_attachment_image_url($org_img_id, 'medium') : '';
        $org_name   = get_post_meta($post->ID, '_ctt_organizer_name', true);
        $org_creds  = get_post_meta($post->ID, '_ctt_organizer_creds', true);
        $org_linked = get_post_meta($post->ID, '_ctt_organizer_linkedin', true);
        $time_label = get_post_meta($post->ID, '_ctt_time_label', true);
        ?>
        <style>
            .ctt-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
            .ctt-field label{font-weight:600;display:block;margin-bottom:4px}
            .ctt-field input,.ctt-field select,.ctt-field textarea{width:100%}
            .ctt-hint{color:#646970;font-size:12px;margin-top:4px}
        </style>
        <div class="ctt-grid">
            <div class="ctt-field">
                <label for="ctt_date">Date</label>
                <select id="ctt_date" name="ctt_date">
                    <option value="2026-05-12" <?php selected($date, '2026-05-12'); ?>>12.05.2026</option>
                    <option value="2026-05-13" <?php selected($date, '2026-05-13'); ?>>13.05.2026</option>
                </select>
            </div>
            <div class="ctt-field">
                <label for="ctt_track">Starting Track (1–6)</label>
                <select id="ctt_track" name="ctt_track">
                    <?php for($i=1;$i<=6;$i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected((int)$track,$i); ?>>Track <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="ctt-field">
                <label for="ctt_track_span">Track Span (1–6)</label>
                <select id="ctt_track_span" name="ctt_track_span">
                    <?php for($i=1;$i<=6;$i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected((int)$track_span,$i); ?>><?php echo $i . ($i==1 ? ' track' : ' tracks'); ?></option>
                    <?php endfor; ?>
                </select>
                <p class="ctt-hint">How many tracks should this event span horizontally?</p>
            </div>
            <div class="ctt-field">
                <label for="ctt_time_label">Time Label (Optional)</label>
                <input type="text" id="ctt_time_label" name="ctt_time_label" value="<?php echo esc_attr($time_label); ?>" placeholder="e.g., 09:00 - 09:30">
                <p class="ctt-hint">Custom time label on the card only (does not affect left column).</p>
            </div>
            <div class="ctt-field">
                <label for="ctt_start">Start Time (HH:MM)</label>
                <input type="time" id="ctt_start" name="ctt_start" value="<?php echo esc_attr($start); ?>">
            </div>
            <div class="ctt-field">
                <label for="ctt_end">End Time (HH:MM)</label>
                <input type="time" id="ctt_end" name="ctt_end" value="<?php echo esc_attr($end); ?>">
            </div>
            <div class="ctt-field" style="grid-column:1/-1">
                <label for="ctt_speaker">Speaker</label>
                <input type="text" id="ctt_speaker" name="ctt_speaker" value="<?php echo esc_attr($speaker); ?>" placeholder="Jane Doe">
            </div>
            <div class="ctt-field" style="grid-column:1/-1">
                <label for="ctt_room">Room / Location</label>
                <input type="text" id="ctt_room" name="ctt_room" value="<?php echo esc_attr($room); ?>" placeholder="Hall A, Room B, Lab C">
            </div>
            <!-- Organizer image -->
            <div class="ctt-field" style="grid-column:1/-1">
                <label>Organizer Image</label>
                <div id="ctt-org-wrap" style="display:flex;align-items:center;gap:12px">
                    <img id="ctt-org-preview" src="<?php echo esc_url($org_img_url); ?>"
                         style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:1px solid #dfe3ec;<?php echo $org_img_url?'':'display:none;'; ?>">
                    <input type="hidden" id="ctt_org_img" name="ctt_org_img" value="<?php echo esc_attr($org_img_id); ?>">
                    <button type="button" class="button" id="ctt-org-upload">Upload</button>
                    <button type="button" class="button button-link-delete" id="ctt-org-remove" <?php echo $org_img_id?'':'style="display:none"'; ?>>Remove</button>
                </div>
            </div>
            <!-- Organizer name & credentials -->
            <div class="ctt-field" style="grid-column:1/-1">
                <label for="ctt_organizer_name">Organizer Name</label>
                <input type="text" id="ctt_organizer_name" name="ctt_organizer_name" value="<?php echo esc_attr($org_name); ?>" placeholder="e.g., Dr. A. Sharma">
            </div>
            <div class="ctt-field" style="grid-column:1/-1">
                <label for="ctt_organizer_creds">Organizer Credentials</label>
                <textarea id="ctt_organizer_creds" name="ctt_organizer_creds" rows="3" placeholder="e.g., PhD in Computer Science&#10;Professor at XYZ University"><?php echo esc_textarea($org_creds); ?></textarea>
            </div>
            <div class="ctt-field" style="grid-column:1/-1">
                <label for="ctt_organizer_linkedin">LinkedIn URL</label>
                <input type="url" id="ctt_organizer_linkedin" name="ctt_organizer_linkedin" value="<?php echo esc_attr($org_linked); ?>" placeholder="https://linkedin.com/in/username">
            </div>
        </div>
        <?php
    }

    public function render_info_metabox($post) {
        wp_nonce_field('ctt_save_meta','ctt_nonce');
        $date        = get_post_meta($post->ID, '_ctt_info_date', true);
        $position    = get_post_meta($post->ID, '_ctt_info_position', true);
        $bg_color    = get_post_meta($post->ID, '_ctt_info_bg_color', true) ?: '#f0f4f8';
        $text_color  = get_post_meta($post->ID, '_ctt_info_text_color', true) ?: '#1e293b';
        $content     = get_post_meta($post->ID, '_ctt_info_content', true);
        $track_start = get_post_meta($post->ID, '_ctt_info_track_start', true) ?: 1;
        $track_end   = get_post_meta($post->ID, '_ctt_info_track_end', true) ?: 6;
        $time_label  = get_post_meta($post->ID, '_ctt_info_time_label', true);
        $height      = get_post_meta($post->ID, '_ctt_info_height', true) ?: 60;
        ?>
        <style>
            .ctt-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
            .ctt-info-field label{font-weight:600;display:block;margin-bottom:4px}
            .ctt-info-field input,.ctt-info-field select,.ctt-info-field textarea{width:100%}
            .ctt-hint{color:#646970;font-size:12px;margin-top:4px}
            .ctt-color-preview{width:40px;height:40px;border-radius:6px;border:2px solid #ddd;margin-top:4px}
        </style>
        <div class="ctt-info-grid">
            <div class="ctt-info-field">
                <label for="ctt_info_date">Date</label>
                <select id="ctt_info_date" name="ctt_info_date">
                    <option value="2026-05-12" <?php selected($date, '2026-05-12'); ?>>12.05.2026</option>
                    <option value="2026-05-13" <?php selected($date, '2026-05-13'); ?>>13.05.2026</option>
                </select>
            </div>
            <div class="ctt-info-field">
                <label for="ctt_info_position">Position (HH:MM)</label>
                <input type="time" id="ctt_info_position" name="ctt_info_position" value="<?php echo esc_attr($position); ?>">
                <p class="ctt-hint">Approximate vertical position in the timetable (snaps to grid)</p>
            </div>
            <div class="ctt-info-field">
                <label for="ctt_info_height">Height (pixels)</label>
                <input type="number" id="ctt_info_height" name="ctt_info_height" value="<?php echo esc_attr($height); ?>" min="30" step="10">
                <p class="ctt-hint">Height of the info block in pixels (default: 60)</p>
            </div>
            <div class="ctt-info-field">
                <label for="ctt_info_time_label">Time Label (Optional)</label>
                <input type="text" id="ctt_info_time_label" name="ctt_info_time_label" value="<?php echo esc_attr($time_label); ?>" placeholder="e.g., Lunch Break">
                <p class="ctt-hint">Custom label shown in the left time column</p>
            </div>
            <div class="ctt-info-field">
                <label for="ctt_info_track_start">Track Start</label>
                <select id="ctt_info_track_start" name="ctt_info_track_start">
                    <?php for($i=1;$i<=6;$i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected((int)$track_start,$i); ?>>Track <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="ctt-info-field">
                <label for="ctt_info_track_end">Track End</label>
                <select id="ctt_info_track_end" name="ctt_info_track_end">
                    <?php for($i=1;$i<=6;$i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected((int)$track_end,$i); ?>>Track <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="ctt-info-field">
                <label for="ctt_info_bg_color">Background Color</label>
                <input type="color" id="ctt_info_bg_color" name="ctt_info_bg_color" value="<?php echo esc_attr($bg_color); ?>">
                <div class="ctt-color-preview" style="background-color:<?php echo esc_attr($bg_color); ?>"></div>
            </div>
            <div class="ctt-info-field">
                <label for="ctt_info_text_color">Text Color</label>
                <input type="color" id="ctt_info_text_color" name="ctt_info_text_color" value="<?php echo esc_attr($text_color); ?>">
                <div class="ctt-color-preview" style="background-color:<?php echo esc_attr($text_color); ?>"></div>
            </div>
            <div class="ctt-info-field" style="grid-column:1/-1">
                <label for="ctt_info_content">Additional Content (Optional)</label>
                <textarea id="ctt_info_content" name="ctt_info_content" rows="3" placeholder="Optional subtitle or description"><?php echo esc_textarea($content); ?></textarea>
            </div>
        </div>
        <?php
    }

    /*** Save Meta ***/
    public function save_meta($post_id) {
        if (!isset($_POST['ctt_nonce']) || !wp_verify_nonce($_POST['ctt_nonce'], 'ctt_save_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $post_type = get_post_type($post_id);

        if ($post_type === self::CPT) {
            $fields = ['date','start','end','track','track_span','speaker','room','organizer_img','organizer_name','organizer_creds','organizer_linkedin','time_label'];
            foreach ($fields as $f) {
                $key = 'ctt_'.$f;
                if (isset($_POST[$key])) {
                    update_post_meta($post_id, '_'.$key, sanitize_text_field($_POST[$key]));
                }
            }
        }

        if ($post_type === self::CPT_INFO) {
            $info_fields = ['date','position','bg_color','text_color','content','track_start','track_end','time_label','height'];
            foreach ($info_fields as $f) {
                $key = 'ctt_info_'.$f;
                if (isset($_POST[$key])) {
                    update_post_meta($post_id, '_'.$key, sanitize_text_field($_POST[$key]));
                }
            }
        }
    }

    /*** Assets ***/
    public function enqueue_assets() {
        if (!has_shortcode(get_post()->post_content ?? '', 'ctt_timetable')) return;

        wp_enqueue_style('ctt-styles', plugins_url('ctt-styles.css', __FILE__), [], '2.0.1');
        wp_enqueue_script('ctt-script', plugins_url('ctt-script.js', __FILE__), [], '2.0.1', true);
        
        wp_localize_script('ctt-script', 'CTT', [
            'rest'  => rest_url(self::NS . '/events'),
            'dates' => rest_url(self::NS . '/dates'),
        ]);
    }

    public function admin_assets($hook) {
        if (!in_array($hook, ['post.php','post-new.php'])) return;
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, [self::CPT, self::CPT_INFO])) return;

        wp_enqueue_media();
        ?>
        <script>
        jQuery(function($) {
            var frame;
            $('#ctt-org-upload').on('click', function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({ title: 'Select Organizer Image', button: { text: 'Use this image' }, multiple: false });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#ctt_org_img').val(attachment.id);
                    $('#ctt-org-preview').attr('src', attachment.url).show();
                    $('#ctt-org-remove').show();
                });
                frame.open();
            });
            $('#ctt-org-remove').on('click', function(e) {
                e.preventDefault();
                $('#ctt_org_img').val('');
                $('#ctt-org-preview').hide();
                $(this).hide();
            });
        });
        </script>
        <?php
    }

    /*** Shortcode ***/
    public function shortcode($atts) {
        $atts = shortcode_atts([
            'date'  => '2026-05-12',
            'start' => '09:00',
            'end'   => '18:00',
            'slot'  => '30',
        ], $atts);

        ob_start();
        ?>
        <div class="ctt-timetable-root" data-rest="<?php echo esc_url(rest_url(self::NS . '/events')); ?>"
             data-initial-date="<?php echo esc_attr($atts['date']); ?>"
             data-start="<?php echo esc_attr($atts['start']); ?>"
             data-end="<?php echo esc_attr($atts['end']); ?>"
             data-slot="<?php echo esc_attr($atts['slot']); ?>">
            <div class="ctt-controls">
                <label for="ctt-date-input" class="ctt-date-label">Date:</label>
                <select id="ctt-date-input" class="ctt-date-input">
                    <option value="2026-05-12">12.05.2026</option>
                    <option value="2026-05-13">13.05.2026</option>
                </select>
            </div>
            <div class="ctt-timetable-wrapper">
                <div class="ctt-time-panel">
                    <div class="ctt-time-header">Time</div>
                    <div class="ctt-time-slots"></div>
                </div>
                <div class="ctt-content-panel">
                    <div class="ctt-track-headers">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <div class="ctt-track-header">Track <?php echo $i; ?></div>
                        <?php endfor; ?>
                    </div>
                    <div class="ctt-track-grid"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /*** REST API ***/
    public function register_routes() {
        register_rest_route(self::NS, '/events', [
            'methods' => 'GET',
            'callback' => [$this, 'get_events'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, '/dates', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dates'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_events($request) {
        $date = $request->get_param('date');
        if (!$date) return new WP_Error('no_date', 'Date parameter required', ['status' => 400]);

        // Events
        $events_query = new WP_Query([
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [['key' => '_ctt_date', 'value' => $date, 'compare' => '=']],
            'orderby'        => 'meta_value',
            'meta_key'       => '_ctt_start',
            'order'          => 'ASC',
        ]);

        $events = [];
        if ($events_query->have_posts()) {
            while ($events_query->have_posts()) {
                $events_query->the_post();
                $pid = get_the_ID();

                $org_img_id  = (int) get_post_meta($pid, '_ctt_organizer_img', true);
                $org_img_url = $org_img_id ? wp_get_attachment_image_url($org_img_id, 'medium') : '';
                $org_img_alt = $org_img_id ? get_post_meta($org_img_id, '_wp_attachment_image_alt', true) : '';

                // Get categories
                $categories = wp_get_post_terms($pid, self::TAX, ['fields' => 'names']);

                $events[] = [
                    'id'         => $pid,
                    'title'      => get_the_title(),
                    'content'    => apply_filters('the_content', get_the_content()),
                    'start'      => get_post_meta($pid, '_ctt_start', true),
                    'end'        => get_post_meta($pid, '_ctt_end', true),
                    'track'      => (int) get_post_meta($pid, '_ctt_track', true),
                    'track_span' => (int) (get_post_meta($pid, '_ctt_track_span', true) ?: 1),
                    'speaker'    => get_post_meta($pid, '_ctt_speaker', true),
                    'room'       => get_post_meta($pid, '_ctt_room', true),
                    'time_label' => get_post_meta($pid, '_ctt_time_label', true),
                    'categories' => $categories,
                    'organizer'  => [
                        'img'  => ['url' => $org_img_url, 'alt' => $org_img_alt],
                        'name' => get_post_meta($pid, '_ctt_organizer_name', true),
                        'creds'=> get_post_meta($pid, '_ctt_organizer_creds', true),
                        'linkedin' => get_post_meta($pid, '_ctt_organizer_linkedin', true),
                    ],
                ];
            }
            wp_reset_postdata();
        }

        // Info blocks - now independent from events
        $info_query = new WP_Query([
            'post_type'      => self::CPT_INFO,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [['key' => '_ctt_info_date', 'value' => $date, 'compare' => '=']],
            'orderby'        => 'meta_value',
            'meta_key'       => '_ctt_info_position',
            'order'          => 'ASC',
        ]);

        $info_blocks = [];
        if ($info_query->have_posts()) {
            while ($info_query->have_posts()) {
                $info_query->the_post();
                $pid = get_the_ID();
                $info_blocks[] = [
                    'id'          => $pid,
                    'title'       => get_the_title(),
                    'position'    => get_post_meta($pid, '_ctt_info_position', true),
                    'bg_color'    => get_post_meta($pid, '_ctt_info_bg_color', true) ?: '#f0f4f8',
                    'text_color'  => get_post_meta($pid, '_ctt_info_text_color', true) ?: '#1e293b',
                    'content'     => get_post_meta($pid, '_ctt_info_content', true),
                    'track_start' => (int) (get_post_meta($pid, '_ctt_info_track_start', true) ?: 1),
                    'track_end'   => (int) (get_post_meta($pid, '_ctt_info_track_end', true)   ?: 6),
                    'time_label'  => get_post_meta($pid, '_ctt_info_time_label', true) ?: '',
                    'height'      => (int) (get_post_meta($pid, '_ctt_info_height', true) ?: 60),
                ];
            }
            wp_reset_postdata();
        }

        return ['events' => $events, 'info_blocks' => $info_blocks];
    }

    public function get_dates($request) {
        return ['dates' => ['2026-05-12', '2026-05-13']];
    }

    /*** Admin Columns ***/
    public function admin_columns($cols) {
        $new = [];
        $new['cb']       = $cols['cb'];
        $new['title']    = $cols['title'];
        $new['ctt_date'] = 'Date';
        $new['ctt_time'] = 'Time';
        $new['ctt_track']= 'Track';
        $new['taxonomy-'.self::TAX] = 'Categories';
        $new['date']     = $cols['date'];
        return $new;
    }

    public function admin_column_content($col, $post_id) {
        if ($col === 'ctt_date') {
            $d = get_post_meta($post_id, '_ctt_date', true);
            echo $d ? esc_html(date('d.m.Y', strtotime($d))) : '—';
        }
        if ($col === 'ctt_time') {
            $s = get_post_meta($post_id, '_ctt_start', true);
            $e = get_post_meta($post_id, '_ctt_end', true);
            echo $s && $e ? esc_html($s.' – '.$e) : '—';
        }
        if ($col === 'ctt_track') {
            $t = (int) get_post_meta($post_id, '_ctt_track', true);
            $span = (int) (get_post_meta($post_id, '_ctt_track_span', true) ?: 1);
            if ($span > 1) {
                $end_track = min(6, $t + $span - 1);
                echo 'Track ' . esc_html($t) . '–' . esc_html($end_track);
            } else {
                echo $t ? 'Track '.esc_html($t) : '—';
            }
        }
    }

    public function admin_columns_info($cols) {
        $new = [];
        $new['cb']            = $cols['cb'];
        $new['title']         = $cols['title'];
        $new['ctt_info_date'] = 'Date';
        $new['ctt_info_pos']  = 'Position';
        $new['date']          = $cols['date'];
        return $new;
    }

    public function admin_column_content_info($col, $post_id) {
        if ($col === 'ctt_info_date') {
            $d = get_post_meta($post_id, '_ctt_info_date', true);
            echo $d ? esc_html(date('d.m.Y', strtotime($d))) : '—';
        }
        if ($col === 'ctt_info_pos') {
            $p = get_post_meta($post_id, '_ctt_info_position', true);
            echo $p ? esc_html($p) : '—';
        }
    }

    /*** Elementor Dynamic Tags Support ***/
    public function register_elementor_tags($dynamic_tags) {
        // Check if Elementor is active
        if (!did_action('elementor/loaded')) return;

        // Register custom tag group
        \Elementor\Plugin::$instance->dynamic_tags->register_group('ctt-fields', [
            'title' => 'Conference Timetable Fields'
        ]);

        // Register individual tags
        require_once plugin_dir_path(__FILE__) . 'elementor-tags.php';
        
        $dynamic_tags->register(new \CTT_Event_Title_Tag());
        $dynamic_tags->register(new \CTT_Event_Date_Tag());
        $dynamic_tags->register(new \CTT_Event_Start_Time_Tag());
        $dynamic_tags->register(new \CTT_Event_End_Time_Tag());
        $dynamic_tags->register(new \CTT_Event_Speaker_Tag());
        $dynamic_tags->register(new \CTT_Event_Room_Tag());
        $dynamic_tags->register(new \CTT_Event_Track_Tag());
        $dynamic_tags->register(new \CTT_Event_Content_Tag());
        $dynamic_tags->register(new \CTT_Event_Organizer_Name_Tag());
        $dynamic_tags->register(new \CTT_Event_Organizer_Image_Tag());
        $dynamic_tags->register(new \CTT_Event_Categories_Tag());
    }

    /*** Activation ***/
    public function on_activate() {
        $this->register_cpt();
        flush_rewrite_rules();
    }
}

new CTT_Plugin();
